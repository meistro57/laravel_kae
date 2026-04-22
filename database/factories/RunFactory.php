<?php

namespace Database\Factories;

use App\Data\RunSettings;
use App\Models\Run;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class RunFactory extends Factory
{
    protected $model = Run::class;

    public function definition(): array
    {
        return [
            'seed'         => $this->faker->randomElement([
                'consciousness and emergence',
                'quantum gravity',
                'Roman agricultural economics',
                'neural plasticity',
                'Pythagorean mathematics',
            ]),
            'status'       => 'pending',
            'started_at'   => null,
            'completed_at' => null,
            'report_text'  => null,
            'run_id_go'    => 'run_' . now()->format('Ymd') . '_' . Str::random(6),
            'settings'     => RunSettings::from([]),
        ];
    }

    public function running(): static
    {
        return $this->state(fn () => [
            'status'     => 'running',
            'started_at' => now()->subMinutes(rand(1, 30)),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status'       => 'completed',
            'started_at'   => now()->subHour(),
            'completed_at' => now()->subMinutes(5),
            'report_text'  => "Run completed. Found {$this->faker->numberBetween(50, 300)} concept nodes across {$this->faker->numberBetween(3, 15)} cycles.",
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status'     => 'failed',
            'started_at' => now()->subMinutes(10),
        ]);
    }
}
