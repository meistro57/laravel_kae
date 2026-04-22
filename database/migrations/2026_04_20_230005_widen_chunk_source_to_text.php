<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chunks', function (Blueprint $table) {
            $table->text('source')->nullable()->change();
            $table->text('run_topic')->nullable()->change();
        });
    }

    public function down(): void {}
};
