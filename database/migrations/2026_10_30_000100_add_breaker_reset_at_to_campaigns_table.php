<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            // Reprise après un arrêt : le taux de rebond n'est plus mesuré que sur les envois planifiés depuis.
            $table->timestamp('breaker_reset_at')->nullable()->after('consecutive_failures');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn('breaker_reset_at');
        });
    }
};
