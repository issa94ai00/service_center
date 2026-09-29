<?php

namespace Database\Factories;

use App\Models\MaterialCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MaterialCategory> */
class MaterialCategoryFactory extends Factory
{
    protected $model = MaterialCategory::class;

    public function definition(): array
    {
        return [
            'name' => 'مواد '.$this->faker->unique()->numberBetween(1, 100000),
            'parent_id' => null,
            'is_active' => true,
        ];
    }
}
