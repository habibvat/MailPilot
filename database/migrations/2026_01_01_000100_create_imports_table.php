<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imports', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->string('path');
            $table->json('mapping')->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('imported')->default(0);
            $table->unsignedInteger('rejected')->default(0);
            $table->unsignedInteger('duplicates')->default(0);
            $table->string('status')->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imports');
    }
};
