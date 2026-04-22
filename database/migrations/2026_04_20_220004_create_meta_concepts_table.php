<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_concepts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('qdrant_point_id')->unique();
            $table->string('concept')->unique()->index();
            $table->timestamp('first_seen_at')->nullable();
            $table->float('total_weight')->default(0.0);
            $table->float('avg_anomaly')->default(0.0);
            $table->json('domains')->nullable();
            $table->boolean('is_attractor')->default(false)->index();
            $table->unsignedInteger('occurrence_count')->default(0);
            $table->json('run_occurrences')->nullable();
            $table->timestamp('synced_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_concepts');
    }
};
