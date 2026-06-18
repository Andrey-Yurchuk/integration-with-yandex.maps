<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->unsignedInteger('blocked_attempts')->default(0)->after('last_sync_error');
            $table->timestamp('blocked_until')->nullable()->after('blocked_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['blocked_attempts', 'blocked_until']);
        });
    }
};
