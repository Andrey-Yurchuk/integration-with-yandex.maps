<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedBigInteger('reviews_found')->default(0);
            $table->unsignedBigInteger('reviews_saved')->default(0);
            $table->unsignedBigInteger('ratings_count')->nullable();
            $table->unsignedBigInteger('reviews_count')->nullable();
            $table->string('error_type')->nullable();
            $table->text('error_message')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'started_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_sync_runs');
    }
};
