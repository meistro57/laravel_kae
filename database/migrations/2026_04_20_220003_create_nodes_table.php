<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nodes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('qdrant_point_id')->unique();
            // No FK constraint — see chunks migration comment.
            $table->uuid('run_id')->index();
            $table->string('label');
            $table->string('domain');
            $table->float('weight');
            $table->boolean('anomaly')->default(false)->index();
            $table->json('sources')->nullable();
            $table->unsignedInteger('cycle');
            $table->timestamp('synced_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nodes');
    }
};
