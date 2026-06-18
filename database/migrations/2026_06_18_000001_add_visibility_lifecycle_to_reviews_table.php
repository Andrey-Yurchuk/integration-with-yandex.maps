<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->timestamp('last_seen_at')->nullable()->after('raw_payload');
            $table->timestamp('missing_since')->nullable()->after('last_seen_at');
            $table->boolean('is_visible')->default(true)->after('missing_since');

            $table->dropUnique(['organization_id', 'external_id']);
            $table->index(['organization_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'external_id']);
            $table->unique(['organization_id', 'external_id']);

            $table->dropColumn(['last_seen_at', 'missing_since', 'is_visible']);
        });
    }
};
