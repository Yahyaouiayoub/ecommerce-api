<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'name_en',
        'name_fr',
        'name_ar',
        'name_es',
        'slug',
        'description',
        'image',
        'is_active',
    ];

    // =========================
    // RELATIONSHIPS
    // =========================
    public function products()
    {
        return $this->hasMany(Product::class);
    }

    // =========================
    // HELPERS
    // =========================
    /**
     * Get the translated name for a given locale. Falls back to default name.
     */
    public function getNameForLocale(string $locale): ?string
    {
        $field = "name_{$locale}";
        return $this->$field ?? $this->name;
    }
}
