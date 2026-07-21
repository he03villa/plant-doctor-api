<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category',
    ];

    public function diseases(): BelongsToMany
    {
        return $this->belongsToMany(Disease::class, 'disease_products');
    }
}
