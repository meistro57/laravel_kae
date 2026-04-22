<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meta_concepts', function (Blueprint $table) {
            $table->dropUnique(['concept']);
            $table->text('concept')->change();
            $table->unique('concept');
        });
    }

    public function down(): void {}
};
