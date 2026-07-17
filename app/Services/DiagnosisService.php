<?php

namespace App\Services;

use App\Models\Diagnosis;
use App\Models\Disease;
use App\Models\Plant;
use App\Models\User;

class DiagnosisService
{
    public function __construct(
        private PlantNetService $plantNetService,
        private GroqService $groqService,
        private FileStorageService $storage
    ) {}

    public function create(User $user, ?Plant $plant, array $data): Diagnosis
    {
        $image = $data['image'];

        $imagePath = $this->storage->store($image, 'diagnoses');

        $filePath = $this->storage->getFilePath($imagePath);
        $organ = $data['organ'] ?? 'leaf';
        $plantResult = $this->plantNetService->identify($filePath, $organ);

        $speciesContext = $plantResult['species'] ?? 'desconocida';
        $diseaseResult = $this->groqService->diagnose($filePath, $speciesContext);

        $diseaseId = $this->matchDiseaseFromCatalog($diseaseResult);

        return Diagnosis::create([
            'plant_id' => $plant?->id,
            'disease_id' => $diseaseId,
            'user_id' => $user->id,
            'confidence_score' => $diseaseResult['confidence'] ?? 0,
            'notes' => $data['notes'] ?? null,
            'image_path' => $imagePath,
            'status' => 'completed',
            'ai_provider' => 'groq',
            'species_name' => $plantResult['species'],
            'species_common_names' => $plantResult['common_names'],
            'disease_name_detected' => $diseaseResult['disease_name'],
            'disease_name_scientific' => $diseaseResult['disease_name_scientific'],
            'disease_severity' => $diseaseResult['severity'],
            'symptoms_observed' => $diseaseResult['symptoms_observed'],
            'treatment_recommendation' => $diseaseResult['treatment'],
            'prevention_recommendation' => $diseaseResult['prevention'],
            'ai_raw_response' => $diseaseResult,
        ]);
    }

    public function requestExpertReview(Diagnosis $diagnosis): Diagnosis
    {
        $diagnosis->update([
            'status' => 'pending_review',
        ]);

        return $diagnosis;
    }

    private function matchDiseaseFromCatalog(array $diseaseResult): ?int
    {
        if (empty($diseaseResult['disease_name_scientific']) && empty($diseaseResult['disease_name'])) {
            return null;
        }

        $disease = null;

        if (!empty($diseaseResult['disease_name_scientific'])) {
            $disease = Disease::where('scientific_name', $diseaseResult['disease_name_scientific'])->first();
        }

        if (!$disease && !empty($diseaseResult['disease_name'])) {
            $disease = Disease::where('name', 'LIKE', '%' . $diseaseResult['disease_name'] . '%')->first();
        }

        return $disease?->id;
    }
}
