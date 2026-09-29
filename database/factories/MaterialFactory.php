<?php

namespace Database\Factories;

use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\ServiceUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Material> */
class MaterialFactory extends Factory
{
    protected $model = Material::class;

    public function definition(): array
    {
        return [
            'category_id' => MaterialCategory::factory(),
            'name' => 'مادة '.$this->faker->unique()->numberBetween(1, 100000),
            'sku' => strtoupper($this->faker->unique()->bothify('SKU-####')),
            'unit_id' => ServiceUnit::factory(),
            'cost_price' => '10.00',
            'sale_price' => '20.00',
            'min_stock' => '0.000',
            'stock_quantity' => '0.000',
            'is_active' => true,
        ];
    }

    public function inStock(string $quantity): static
    {
        return $this->state(fn () => ['stock_quantity' => $quantity]);
    }

    public function withMinimum(string $minimum): static
    {
        return $this->state(fn () => ['min_stock' => $minimum]);
    }
}
