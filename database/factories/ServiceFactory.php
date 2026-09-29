<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\ServiceUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Service> */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return [
            'name' => 'خدمة '.$this->faker->unique()->numberBetween(1, 100000),
            'description' => null,
            'type' => 'METER_BASED',
            'unit_id' => ServiceUnit::factory(),
            'default_price' => '5.00',
            'is_active' => true,
        ];
    }

    public function manual(): static
    {
        return $this->state(fn () => ['type' => 'MANUAL', 'unit_id' => null]);
    }
}
