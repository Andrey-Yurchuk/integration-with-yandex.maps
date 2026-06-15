<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicateOrganizationIds = DB::table('organization_sync_runs')
            ->select('organization_id')
            ->groupBy('organization_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('organization_id');

        foreach ($duplicateOrganizationIds as $organizationId) {
            $latestId = DB::table('organization_sync_runs')
                ->where('organization_id', $organizationId)
                ->max('id');

            if ($latestId === null) {
                continue;
            }

            DB::table('organization_sync_runs')
                ->where('organization_id', $organizationId)
                ->where('id', '!=', $latestId)
                ->delete();
        }

        Schema::table('organization_sync_runs', function (Blueprint $table) {
            $table->unique('organization_id');
        });
    }

    public function down(): void
    {
        Schema::table('organization_sync_runs', function (Blueprint $table) {
            $table->dropUnique(['organization_id']);
        });
    }
};
