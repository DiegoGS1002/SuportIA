<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('type')->default('knowledge_base')->after('chat_id')->index();
            $table->string('disk')->default('local')->after('file_path');
            $table->string('mime_type')->nullable()->after('disk');
            $table->string('software')->nullable()->after('mime_type')->index();
            $table->string('title')->nullable()->after('software');
            $table->string('status')->default('uploaded')->after('title')->index();
            $table->text('error_message')->nullable()->after('status');
            $table->unsignedInteger('chunks_count')->default(0)->after('error_message');
        });

        DB::table('documents')
            ->whereNotNull('chat_id')
            ->update(['type' => 'chat_export', 'status' => 'indexed']);
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropIndex(['software']);
            $table->dropIndex(['status']);
            $table->dropColumn([
                'type',
                'disk',
                'mime_type',
                'software',
                'title',
                'status',
                'error_message',
                'chunks_count',
            ]);
        });
    }
};
