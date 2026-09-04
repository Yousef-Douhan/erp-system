<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\ProductBatch;

class ProductBatchFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ProductBatch::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'product_id' => fake()->word(),
            'warehouse_id' => fake()->word(),
            'batch_number' => fake()->word(),
            'expiry_date' => fake()->date(),
            'cost_price' => fake()->randomFloat(2, 0, 9999999999999.99),
            'selling_price' => fake()->randomFloat(2, 0, 9999999999999.99),
            'quantity' => fake()->numberBetween(-10000, 10000),
        ];
    }
}
