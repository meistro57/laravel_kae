<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_results', function (Blueprint $table) {
            $table->id();
            $table->timestamp('run_timestamp');
            $table->json('summary')->nullable();
            $table->unsignedInteger('issues_found')->default(0);
            $table->unsignedInteger('issues_repaired')->default(0);
            $table->json('details')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_results');
    }
};
