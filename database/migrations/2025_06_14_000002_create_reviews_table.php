<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('external_id')->nullable();
            $table->string('content_hash');
            $table->string('author_name');
            $table->string('author_avatar_url')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('text')->nullable();
            $table->unsignedSmallInteger('rating')->nullable();
            $table->jsonb('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'reviewed_at']);
            $table->unique(['organization_id', 'content_hash']);
            $table->unique(['organization_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
