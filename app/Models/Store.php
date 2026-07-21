<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Store extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'business_name',
        'tax_id',
        'address',
        'phone',
        'business_phone',
        'business_email',
        'latitude',
        'longitude',
        'is_active',
        'is_premium',
        'onboarding_completed',
        'sync_to_map',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'location' => 'point',
        'is_active' => 'boolean',
        'is_premium' => 'boolean',
        'onboarding_completed' => 'boolean',
        'sync_to_map' => 'boolean',
    ];

    public function storeProducts(): HasMany
    {
        return $this->hasMany(StoreProduct::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
