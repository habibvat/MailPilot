<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_sends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            // queued → dispatched → sending → sent | failed | bounced | skipped
            $table->string('status')->default('queued');
            $table->text('error')->nullable();
            $table->string('message_id')->nullable()->index();
            $table->uuid('dispatch_token')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'contact_id']);
            $table->index(['campaign_id', 'status']);
            $table->index('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_sends');
    }
};
