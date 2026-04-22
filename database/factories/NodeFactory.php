<?php

namespace Database\Factories;

use App\Models\Node;
use Illuminate\Database\Eloquent\Factories\Factory;

class NodeFactory extends Factory
{
    protected $model = Node::class;

    private static int $pointIdCounter = 5000000;

    public function definition(): array
    {
        $domains = [
            'Roman History', 'Neuroscience', 'Quantum Physics',
            'Medical Research', 'Philosophy', 'Consciousness Studies',
        ];

        return [
            'qdrant_point_id' => self::$pointIdCounter++,
            'run_id'          => \App\Models\Run::factory(),
            'label'           => $this->faker->randomElement([
                'emergent complexity',
                'synaptic plasticity',
                'Marcus Aurelius',
                'wave function collapse',
                'Platonic forms',
                'mitochondrial DNA',
            ]) . ' ' . $this->faker->word(),
            'domain'          => $this->faker->randomElement($domains),
            'weight'          => $this->faker->randomFloat(3, 0.1, 5.0),
            'anomaly'         => $this->faker->boolean(15),
            'sources'         => [
                'https://arxiv.org/abs/2301.' . $this->faker->numberBetween(10000, 99999),
            ],
            'cycle'           => $this->faker->numberBetween(1, 20),
            'synced_at'       => now(),
        ];
    }

    public function anomalous(): static
    {
        return $this->state(fn () => [
            'anomaly' => true,
            'weight'  => $this->faker->randomFloat(3, 2.0, 8.0),
        ]);
    }
}
