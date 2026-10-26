<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('company_name')->nullable();
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('sector')->nullable()->index();
            $table->string('wilaya')->nullable()->index();
            $table->string('website')->nullable();
            $table->json('tags')->nullable();
            $table->string('verify_status')->default('pending')->index();
            $table->string('verify_reason')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamp('last_contacted_at')->nullable()->index();
            $table->timestamp('replied_at')->nullable();
            $table->foreignId('import_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
