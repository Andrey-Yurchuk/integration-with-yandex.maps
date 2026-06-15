<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_sync_runs', function (Blueprint $table) {
            $table->string('source_url')->nullable()->after('organization_id');
            $table->string('normalized_url')->nullable()->after('source_url');
            $table->string('yandex_object_id')->nullable()->after('normalized_url');
            $table->string('organization_title')->nullable()->after('yandex_object_id');
        });
    }

    public function down(): void
    {
        Schema::table('organization_sync_runs', function (Blueprint $table) {
            $table->dropColumn([
                'source_url',
                'normalized_url',
                'yandex_object_id',
                'organization_title',
            ]);
        });
    }
};
