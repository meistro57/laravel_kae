<?php

namespace Database\Factories;

use App\Models\GutenbergBlacklist;
use Illuminate\Database\Eloquent\Factories\Factory;

class GutenbergBlacklistFactory extends Factory
{
    protected $model = GutenbergBlacklist::class;

    public function definition(): array
    {
        return [
            'title'          => $this->faker->unique()->sentence(3),
            'reason'         => 'Title-content mismatch detected in Project Gutenberg',
            'detection_date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'active'         => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
