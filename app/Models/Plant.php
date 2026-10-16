<?php

namespace App\Models;

use App\Services\FileStorageService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plant extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'species',
        'description',
        'image_path',
        'latitude',
        'longitude',
        'location',
        'country_id',
        'state_id',
        'city_id',
        'country_name',
        'state_name',
        'city_name',
        'status',
    ];

    protected $hidden = [
        'location',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function diagnoses(): HasMany
    {
        return $this->hasMany(Diagnosis::class);
    }

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        return app(FileStorageService::class)->getUrl($this->image_path);
    }

    public function publication()
    {
        return $this->morphOne(Publication::class, 'publishable');
    }
}
