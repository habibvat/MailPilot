<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smtp_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->string('host');
            $table->unsignedInteger('port')->default(587);
            $table->string('encryption')->default('tls');
            $table->string('username')->nullable();
            $table->text('password')->nullable();
            $table->string('from_name');
            $table->string('from_email');
            $table->unsignedInteger('hourly_limit')->default(10);
            $table->unsignedInteger('daily_limit')->default(30);
            $table->string('imap_host')->nullable();
            $table->unsignedInteger('imap_port')->nullable();
            $table->string('imap_username')->nullable();
            $table->text('imap_password')->nullable();
            $table->unsignedBigInteger('imap_last_uid')->nullable();
            $table->timestamp('imap_checked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smtp_accounts');
    }
};
