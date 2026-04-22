<?php

namespace Database\Factories;

use App\Models\MetaConcept;
use Illuminate\Database\Eloquent\Factories\Factory;

class MetaConceptFactory extends Factory
{
    protected $model = MetaConcept::class;

    private static int $pointIdCounter = 9000000;

    public function definition(): array
    {
        $occurrences = $this->faker->numberBetween(1, 8);

        return [
            'qdrant_point_id'  => self::$pointIdCounter++,
            'concept'          => $this->faker->unique()->randomElement([
                'emergent complexity', 'synaptic plasticity', 'Marcus Aurelius',
                'wave function collapse', 'Platonic forms', 'mitochondrial DNA',
                'strange attractor', 'Gödel incompleteness', 'cellular automata',
                'qualia', 'holographic principle', 'epigenetic inheritance',
            ]) . ' ' . $this->faker->unique()->word(),
            'first_seen_at'    => $this->faker->dateTimeBetween('-6 months', '-1 week'),
            'total_weight'     => $this->faker->randomFloat(3, 0.5, 20.0),
            'avg_anomaly'      => $this->faker->randomFloat(3, 0.0, 0.9),
            'domains'          => $this->faker->randomElements(
                ['Roman History', 'Neuroscience', 'Quantum Physics', 'Philosophy'],
                $this->faker->numberBetween(1, 3)
            ),
            'is_attractor'     => $occurrences >= 3,
            'occurrence_count' => $occurrences,
            'run_occurrences'  => array_map(fn ($i) => [
                'run_id'  => 'run_' . $this->faker->numerify('########'),
                'cycle'   => $this->faker->numberBetween(1, 15),
                'weight'  => $this->faker->randomFloat(3, 0.1, 5.0),
                'anomaly' => $this->faker->boolean(20),
            ], range(1, $occurrences)),
            'synced_at'        => now(),
        ];
    }

    public function attractor(): static
    {
        return $this->state(fn () => [
            'is_attractor'     => true,
            'occurrence_count' => $this->faker->numberBetween(3, 10),
        ]);
    }
}
