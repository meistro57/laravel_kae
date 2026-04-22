<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The real kae_lens_findings payload (from Qdrant) has evolved beyond what the
// orientation report described. This migration adds the actual fields emitted by
// the Lens Writer so the sync job can store them faithfully.
// Fields from orientation report that have no real equivalent (finding, density_assessment,
// reasoning_model, anchor_chunk_id) are left in place — they will be null for
// data synced from the current Lens implementation.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('findings', function (Blueprint $table) {
            $table->string('type')->nullable()->after('density_assessment');        // anomaly | finding
            $table->string('batch_id')->nullable()->after('type');
            $table->boolean('reviewed')->default(false)->after('batch_id');
            $table->text('reasoning_trace')->nullable()->after('reviewed');
            $table->text('correction')->nullable()->after('reasoning_trace');
            $table->json('domains')->nullable()->after('correction');
            // Stores embedding_text and any other fields not mapped to their own column.
            $table->json('raw_payload')->nullable()->after('domains');
        });
    }

    public function down(): void
    {
        Schema::table('findings', function (Blueprint $table) {
            $table->dropColumn(['type', 'batch_id', 'reviewed', 'reasoning_trace', 'correction', 'domains', 'raw_payload']);
        });
    }
};
