<?php

namespace Database\Factories;

use App\Models\Chunk;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChunkFactory extends Factory
{
    protected $model = Chunk::class;

    private static int $pointIdCounter = 1000000;

    public function definition(): array
    {
        $domains = [
            'Roman History', 'Neuroscience', 'Quantum Physics',
            'Medical Research', 'Philosophy', 'Software Development',
            'Consciousness Studies', 'Encyclopedia',
        ];

        $sources = [
            'https://arxiv.org/abs/2301.' . $this->faker->numberBetween(10000, 99999),
            'https://en.wikipedia.org/wiki/' . $this->faker->word(),
            'https://pubmed.ncbi.nlm.nih.gov/' . $this->faker->numberBetween(10000000, 39999999),
            'https://gutendex.com/books/' . $this->faker->numberBetween(1, 70000),
        ];

        return [
            'qdrant_point_id'   => self::$pointIdCounter++,
            'run_id'            => null,
            'text'              => $this->faker->sentences(3, true),
            'source'            => $this->faker->randomElement($sources),
            'run_topic'         => $this->faker->randomElement([
                'consciousness and emergence',
                'quantum gravity',
                'Roman agricultural economics',
            ]),
            'semantic_domain'   => $this->faker->randomElement($domains),
            'domain_confidence' => $this->faker->randomFloat(2, 0.55, 0.99),
            'lens_processed'    => false,
            'lens_correction'   => false,
            'synced_at'         => now(),
        ];
    }

    public function processed(): static
    {
        return $this->state(fn () => ['lens_processed' => true]);
    }

    public function correction(): static
    {
        return $this->state(fn () => [
            'lens_processed'  => true,
            'lens_correction' => true,
        ]);
    }
}
