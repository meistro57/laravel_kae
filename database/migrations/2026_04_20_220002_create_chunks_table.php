<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chunks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('qdrant_point_id')->unique();
            // Nullable: chunks may arrive before their run is known to Laravel.
            // No FK constraint by design — enforced after backfill in Phase 2.
            $table->uuid('run_id')->nullable()->index();
            $table->text('text');
            $table->string('source');
            $table->string('run_topic');
            $table->string('semantic_domain');
            $table->float('domain_confidence');
            $table->boolean('lens_processed')->default(false)->index();
            $table->boolean('lens_correction')->default(false);
            $table->timestamp('synced_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chunks');
    }
};
