<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->boolean('is_active')->default(false)->after('user_id');
            $table->index(['user_id', 'is_active']);
        });

        $userIds = DB::table('organizations')
            ->select('user_id')
            ->distinct()
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            $latestId = DB::table('organizations')
                ->where('user_id', $userId)
                ->max('id');

            if ($latestId === null) {
                continue;
            }

            DB::table('organizations')
                ->where('user_id', $userId)
                ->update(['is_active' => false]);

            DB::table('organizations')
                ->where('id', $latestId)
                ->update(['is_active' => true]);
        }

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement(
                'CREATE UNIQUE INDEX organizations_user_active_unique ON organizations (user_id) WHERE is_active = true',
            );
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS organizations_user_active_unique');
        }

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'is_active']);
            $table->dropColumn('is_active');
        });
    }
};
