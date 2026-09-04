<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\Product;

class ProductFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Product::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'organization_id' => fake()->word(),
            'category_id' => fake()->word(),
            'name' => fake()->name(),
            'sku' => fake()->regexify('[A-Za-z0-9]{unique}'),
            'track_expiry' => fake()->boolean(),
            'is_controlled' => fake()->boolean(),
            'is_active' => fake()->boolean(),
        ];
    }
}
