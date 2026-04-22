<?php

namespace Database\Factories;

use App\Models\Finding;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class FindingFactory extends Factory
{
    protected $model = Finding::class;

    public function definition(): array
    {
        $densities = ['very_sparse', 'sparse', 'medium', 'dense', 'very_dense'];
        $models    = ['deepseek/deepseek-r1', 'google/gemini-2.5-flash', 'claude-opus-4-6'];

        return [
            'qdrant_point_id'   => (string) Str::uuid(),
            'run_id'            => null,
            'anchor_chunk_id'   => null,
            'finding'           => $this->faker->paragraph(3),
            'confidence'        => $this->faker->randomFloat(2, 0.55, 0.99),
            'sources'           => [$this->faker->numberBetween(1000, 9999)],
            'density_assessment'=> $this->faker->randomElement($densities),
            'reasoning_model'   => $this->faker->randomElement($models),
            'type'              => $this->faker->randomElement(['synthesis', 'anomaly', 'correction']),
            'batch_id'          => (string) Str::uuid(),
            'reviewed'          => false,
            'reasoning_trace'   => $this->faker->paragraph(2),
            'correction'        => null,
            'domains'           => [$this->faker->word()],
            'raw_payload'       => [],
            'created_at'        => $this->faker->dateTimeBetween('-1 month', 'now'),
            'synced_at'         => now(),
        ];
    }

    public function highConfidence(): static
    {
        return $this->state(fn () => ['confidence' => $this->faker->randomFloat(2, 0.85, 0.99)]);
    }
}
