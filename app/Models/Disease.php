<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Disease extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'scientific_name',
        'description',
        'symptoms',
        'treatment',
        'prevention',
        'image_path',
        'category',
        'severity',
        'is_active',
        'perenual_id',
        'last_synced_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    public function diagnoses(): HasMany
    {
        return $this->hasMany(Diagnosis::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'disease_products');
    }

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image_path) {
            return null;
        }

        return app(\App\Services\FileStorageService::class)->getUrl($this->image_path);
    }

    public function publication()
    {
        return $this->morphOne(Publication::class, 'publishable');
    }
}
