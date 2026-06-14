<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source_url');
            $table->string('normalized_url')->nullable();
            $table->string('yandex_object_id')->nullable();
            $table->string('title')->nullable();
            $table->string('address')->nullable();
            $table->decimal('rating', 3, 2)->nullable();
            $table->unsignedBigInteger('ratings_count')->default(0);
            $table->unsignedBigInteger('reviews_count')->default(0);
            $table->string('sync_status')->default('awaiting');
            $table->timestamp('last_sync_started_at')->nullable();
            $table->timestamp('last_sync_finished_at')->nullable();
            $table->text('last_sync_error')->nullable();
            $table->string('parser_version')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('sync_status');
            // PostgreSQL treats NULL as distinct in unique indexes, so rows without yandex_object_id are allowed
            $table->unique(['user_id', 'yandex_object_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
