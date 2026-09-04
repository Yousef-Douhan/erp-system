<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\Customer;

class CustomerFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Customer::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'organization_id' => fake()->word(),
            'name' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'balance' => fake()->randomFloat(2, 0, 9999999999999.99),
            'credit_limit' => fake()->randomFloat(2, 0, 9999999999999.99),
        ];
    }
}
