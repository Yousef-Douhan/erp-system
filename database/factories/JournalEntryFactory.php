<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\JournalEntry;

class JournalEntryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = JournalEntry::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'organization_id' => fake()->word(),
            'entry_number' => fake()->word(),
            'entry_date' => fake()->date(),
            'description' => fake()->text(),
        ];
    }
}
