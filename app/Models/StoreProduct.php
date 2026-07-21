<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'name',
        'category',
        'sku',
        'sale_price',
        'purchase_price',
        'stock_quantity',
        'min_stock',
        'unit',
        'barcode',
        'description',
        'image_url',
        'is_active',
        'is_visible_on_map',
    ];

    protected $casts = [
        'sale_price' => 'decimal:2',
        'purchase_price' => 'decimal:2',
        'stock_quantity' => 'integer',
        'min_stock' => 'integer',
        'is_active' => 'boolean',
        'is_visible_on_map' => 'boolean',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInStock($query)
    {
        return $query->where('stock_quantity', '>', 0);
    }

    public function scopeVisibleOnMap($query)
    {
        return $query->where('is_visible_on_map', true);
    }
}
