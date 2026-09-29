<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Customer> */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'name' => 'عميل '.$this->faker->unique()->numberBetween(1, 100000),
            'company' => null,
            'phone' => '09'.$this->faker->numerify('########'),
            'opening_balance' => '0.00',
            'is_active' => true,
        ];
    }

    public function withOpeningBalance(string $balance): static
    {
        return $this->state(fn () => ['opening_balance' => $balance]);
    }
}
