<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('template_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('smtp_account_id')->nullable()->constrained()->nullOnDelete();
            $table->json('segment_filters')->nullable();
            $table->unsignedInteger('hourly_limit')->default(10);
            $table->unsignedInteger('daily_limit')->default(30);
            $table->json('send_days')->nullable();
            $table->string('send_start', 5)->default('09:00');
            $table->string('send_end', 5)->default('17:00');
            $table->unsignedInteger('min_delay')->default(60);
            $table->unsignedInteger('max_delay')->default(180);
            $table->string('status')->default('draft')->index();
            $table->string('stop_reason')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->timestamp('next_send_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
