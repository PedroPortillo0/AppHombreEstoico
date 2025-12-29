<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('user_challenge_completions', function (Blueprint $table) {
            // Eliminar challenge_id
            $table->dropColumn('challenge_id');
            
            // Eliminar challenge_title y challenge_description
            $table->dropColumn(['challenge_title', 'challenge_description']);
            
            // Agregar nuevos campos
            $table->string('name')->after('user_id');
            $table->string('level')->after('name');
            $table->text('objective')->after('level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_challenge_completions', function (Blueprint $table) {
            // Restaurar campos anteriores
            $table->string('challenge_id')->after('user_id');
            $table->string('challenge_title')->nullable()->after('challenge_id');
            $table->text('challenge_description')->nullable()->after('challenge_title');
            
            // Eliminar nuevos campos
            $table->dropColumn(['name', 'level', 'objective']);
        });
    }
};
