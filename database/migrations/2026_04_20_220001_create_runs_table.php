<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('seed')->nullable();
            $table->enum('status', ['pending', 'running', 'completed', 'failed'])
                  ->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('report_text')->nullable();
            $table->string('run_id_go')->unique()->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('run_id_go');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('runs');
    }
};
