<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppressions', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('reason');
            $table->timestamps();
        });

        Schema::create('domain_cache', function (Blueprint $table) {
            $table->id();
            $table->string('domain')->unique();
            $table->boolean('has_mx')->default(false);
            $table->boolean('is_disposable')->default(false);
            $table->timestamp('checked_at')->nullable();
        });

        Schema::create('wilayas', function (Blueprint $table) {
            $table->unsignedTinyInteger('code')->primary();
            $table->string('name');
            $table->json('aliases')->nullable();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('wilayas');
        Schema::dropIfExists('domain_cache');
        Schema::dropIfExists('suppressions');
    }
};
