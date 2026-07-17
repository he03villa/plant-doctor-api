<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Diagnosis extends Model
{
    use HasFactory;

    protected $fillable = [
        'plant_id',
        'disease_id',
        'user_id',
        'confidence_score',
        'notes',
        'image_path',
        'status',
        'expert_verified',
        'expert_notes',
        'expert_id',
        'ai_provider',
        'species_name',
        'species_common_names',
        'disease_name_detected',
        'disease_name_scientific',
        'disease_severity',
        'symptoms_observed',
        'treatment_recommendation',
        'prevention_recommendation',
        'ai_raw_response',
    ];

    protected $casts = [
        'confidence_score' => 'float',
        'expert_verified' => 'boolean',
        'species_common_names' => 'array',
        'symptoms_observed' => 'array',
        'ai_raw_response' => 'array',
    ];

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image_path) {
            return null;
        }

        return app(\App\Services\FileStorageService::class)->getUrl($this->image_path);
    }

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }

    public function disease(): BelongsTo
    {
        return $this->belongsTo(Disease::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function expert(): BelongsTo
    {
        return $this->belongsTo(User::class, 'expert_id');
    }
}
