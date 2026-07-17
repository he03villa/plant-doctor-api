<?php

namespace App\Services;

use App\Models\Plant;
use App\Models\User;
use Illuminate\Http\UploadedFile;

class PlantService
{
    public function __construct(
        private CloudinaryService $cloudinary
    ) {}

    public function create(User $user, array $data): Plant
    {
        $plant = new Plant($data);
        $plant->user_id = $user->id;

        if (isset($data['image']) && $data['image'] instanceof UploadedFile) {
            $plant->image_path = $this->cloudinary->upload($data['image'], 'plants');
        }

        $plant->save();

        return $plant;
    }

    public function update(Plant $plant, array $data): Plant
    {
        if (isset($data['image']) && $data['image'] instanceof UploadedFile) {
            if ($plant->image_path) {
                $this->cloudinary->delete($plant->image_path);
            }
            $data['image_path'] = $this->cloudinary->upload($data['image'], 'plants');
            unset($data['image']);
        }

        $plant->update($data);

        return $plant;
    }

    public function delete(Plant $plant): bool
    {
        if ($plant->image_path) {
            $this->cloudinary->delete($plant->image_path);
        }

        return $plant->delete();
    }
}
