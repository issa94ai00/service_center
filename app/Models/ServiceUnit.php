<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceUnit extends Model
{
    use HasFactory;

    protected $fillable = ['name_ar', 'name_en', 'symbol', 'category', 'is_measurable', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_measurable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'unit_id');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class, 'unit_id');
    }
}
