<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\Sale;

class SaleFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Sale::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'organization_id' => fake()->word(),
            'branch_id' => fake()->word(),
            'customer_id' => fake()->word(),
            'invoice_number' => fake()->regexify('[A-Za-z0-9]{unique}'),
            'total' => fake()->randomFloat(2, 0, 9999999999999.99),
            'paid_amount' => fake()->randomFloat(2, 0, 9999999999999.99),
            'status' => fake()->word(),
        ];
    }
}
