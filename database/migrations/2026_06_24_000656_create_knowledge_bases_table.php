<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dimension used for OpenAI embeddings (text-embedding-3-small / large truncated).
     */
    private const int EMBEDDING_DIMENSIONS = 1536;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('knowledge_bases', function (Blueprint $table) {
            $table->id();
            $table->string('software')->index()->comment('home or stella');
            $table->string('title');
            $table->longText('content');
            $table->json('embedding')->nullable()->comment('Fallback storage when VECTOR type is unavailable');
            $table->timestamps();
        });

        // MySQL 9 introduced the native VECTOR type + VECTOR_DISTANCE() function.
        // We upgrade the column when the driver supports it, keeping JSON as a
        // safe fallback for other drivers/versions (e.g. sqlite in local/testing
        // envs, or MySQL < 9 which does not know the VECTOR type yet).
        if (DB::connection()->getDriverName() === 'mysql') {
            try {
                DB::statement(sprintf(
                    'ALTER TABLE knowledge_bases MODIFY embedding VECTOR(%d) NULL',
                    self::EMBEDDING_DIMENSIONS
                ));
            } catch (\Throwable) {
                // MySQL server does not support the VECTOR type (pre-9.0).
                // JSON column already created above will be used as a fallback.
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('knowledge_bases');
    }
};
