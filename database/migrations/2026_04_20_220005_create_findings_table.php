<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('findings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Findings use UUID point IDs in Qdrant (unlike chunks/nodes which use uint64).
            $table->string('qdrant_point_id')->unique();
            // No FK constraints — see chunks migration comment.
            $table->uuid('run_id')->nullable()->index();
            $table->unsignedBigInteger('anchor_chunk_id')->nullable()->index();
            $table->text('finding');
            $table->float('confidence')->index();
            $table->json('sources')->nullable();
            $table->string('density_assessment');
            $table->string('reasoning_model');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('synced_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('findings');
    }
};
