<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->string('status')->default('completed')->after('content')->index();
            $table->uuid('idempotency_key')->nullable()->after('status');
            $table->foreignId('attachment_document_id')
                ->nullable()
                ->after('idempotency_key')
                ->constrained('documents')
                ->nullOnDelete();
            $table->unique(['chat_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropUnique(['chat_id', 'idempotency_key']);
            $table->dropConstrainedForeignId('attachment_document_id');
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'idempotency_key']);
        });
    }
};
