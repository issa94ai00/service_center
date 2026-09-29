<?php

namespace Database\Factories;

use App\Models\ServiceUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ServiceUnit> */
class ServiceUnitFactory extends Factory
{
    protected $model = ServiceUnit::class;

    public function definition(): array
    {
        return [
            'name_ar' => 'قطعة',
            'name_en' => 'Piece',
            'symbol' => 'pc',
            'category' => 'COUNT',
            'is_measurable' => true,
            'is_active' => true,
        ];
    }

    public function meter(): static
    {
        return $this->state(fn () => [
            'name_ar' => 'متر', 'name_en' => 'Meter', 'symbol' => 'm', 'category' => 'LENGTH',
        ]);
    }
}
