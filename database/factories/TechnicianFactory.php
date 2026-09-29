<?php

namespace Database\Factories;

use App\Models\Technician;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Technician> */
class TechnicianFactory extends Factory
{
    protected $model = Technician::class;

    public function definition(): array
    {
        return [
            'name' => 'خبير '.$this->faker->unique()->numberBetween(1, 100000),
            'specialty' => 'شاشات',
            'phone' => null,
            'commission_rate' => '20.00',
            'opening_balance' => '0.00',
            'is_active' => true,
        ];
    }

    public function rate(string $rate): static
    {
        return $this->state(fn () => ['commission_rate' => $rate]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
