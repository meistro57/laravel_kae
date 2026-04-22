<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_cursors', function (Blueprint $table) {
            $table->string('collection_name')->primary();
            $table->timestamp('last_synced_at')->nullable();
            // Stores the Qdrant next_page_offset value for resumable pagination.
            // JSON type preserves the original type: null | int (numeric collections) | string (UUID collections).
            $table->json('last_point_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_cursors');
    }
};
