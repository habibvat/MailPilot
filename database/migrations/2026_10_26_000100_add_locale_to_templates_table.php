<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            // Langue de l'email : pied de page légal, désinscription et valeurs par défaut des variables.
            $table->string('locale', 5)->default('fr')->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropColumn('locale');
        });
    }
};
