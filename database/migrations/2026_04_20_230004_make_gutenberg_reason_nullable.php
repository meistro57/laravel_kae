<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gutenberg_blacklists', function (Blueprint $table) {
            $table->text('reason')->nullable()->change();
            $table->date('detection_date')->nullable()->change();
        });
    }

    public function down(): void {}
};
