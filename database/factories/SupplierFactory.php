<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Supplier> */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        return [
            'name' => 'مورد '.$this->faker->unique()->numberBetween(1, 100000),
            'company' => null,
            'phone' => '01'.$this->faker->numerify('########'),
            'opening_balance' => '0.00',
            'is_active' => true,
        ];
    }

    public function withOpeningBalance(string $balance): static
    {
        return $this->state(fn () => ['opening_balance' => $balance]);
    }
}
