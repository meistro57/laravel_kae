<?php

namespace Database\Factories;

use App\Models\AuditResult;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuditResultFactory extends Factory
{
    protected $model = AuditResult::class;

    public function definition(): array
    {
        $found    = $this->faker->numberBetween(0, 50);
        $repaired = $this->faker->numberBetween(0, $found);

        return [
            'run_timestamp'   => $this->faker->dateTimeBetween('-1 month', 'now'),
            'summary'         => [
                'collection'   => 'kae_chunks',
                'total_scanned'=> $this->faker->numberBetween(100, 5000),
                'issues_found' => $found,
                'repaired'     => $repaired,
            ],
            'issues_found'    => $found,
            'issues_repaired' => $repaired,
            'details'         => array_map(fn () => [
                'point_id' => $this->faker->numberBetween(1000000, 9999999),
                'issue'    => $this->faker->randomElement(['zero_vector', 'missing_payload']),
                'repaired' => $this->faker->boolean(70),
            ], range(1, min($found, 5))),
        ];
    }

    public function clean(): static
    {
        return $this->state(fn () => [
            'issues_found'    => 0,
            'issues_repaired' => 0,
            'details'         => [],
        ]);
    }
}
