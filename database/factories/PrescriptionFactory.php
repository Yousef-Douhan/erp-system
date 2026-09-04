<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\Prescription;

class PrescriptionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Prescription::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'customer_id' => fake()->word(),
            'doctor_name' => fake()->word(),
            'diagnosis' => fake()->text(),
            'image_path' => fake()->regexify('[A-Za-z0-9]{nullable}'),
            'is_filled' => fake()->boolean(),
        ];
    }
}
