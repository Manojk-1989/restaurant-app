<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_events', function (Blueprint $table): void {
            $table->string('event_id', 100)->primary();
            $table->string('event_name', 80);
            $table->string('source', 12);
            $table->timestamp('occurred_at', 3);
            $table->string('visitor_id', 128)->nullable();
            $table->string('session_id', 128)->nullable();
            $table->string('page_path', 512)->nullable();
            $table->json('properties');
            $table->timestamp('received_at')->useCurrent();
            $table->index('occurred_at');
            $table->index(['visitor_id', 'occurred_at']);
            $table->index(['event_name', 'page_path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
    }
};