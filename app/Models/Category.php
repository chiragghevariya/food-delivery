<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    /**
     * Columns that may be mass assigned.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'image',
    ];

    /**
     * All menu items that belong to this category.
     */
    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }
}
