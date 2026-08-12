<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MenuItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category',
        'current_price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'current_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
