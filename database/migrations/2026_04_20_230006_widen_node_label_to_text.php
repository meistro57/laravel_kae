<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->text('label')->change();
            $table->string('domain')->nullable()->change();
        });

        Schema::table('findings', function (Blueprint $table) {
            $table->string('reasoning_model')->nullable()->change();
        });
    }

    public function down(): void {}
};
