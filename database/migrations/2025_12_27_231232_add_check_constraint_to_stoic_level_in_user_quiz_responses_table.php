<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Agrega una restricción CHECK para validar que stoic_level solo contenga valores permitidos
     */
    public function up(): void
    {
        // Para MySQL/MariaDB
        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                ALTER TABLE user_quiz_responses 
                ADD CONSTRAINT chk_stoic_level 
                CHECK (stoic_level IS NULL OR stoic_level IN ('principiante', 'basico_intermedio', 'intermedio', 'intermedio_avanzado', 'avanzado'))
            ");
        }
        
        // Para PostgreSQL
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
                ALTER TABLE user_quiz_responses 
                ADD CONSTRAINT chk_stoic_level 
                CHECK (stoic_level IS NULL OR stoic_level IN ('principiante', 'basico_intermedio', 'intermedio', 'intermedio_avanzado', 'avanzado'))
            ");
        }
        
        // Para SQLite (soporte limitado, pero intentamos)
        if (DB::getDriverName() === 'sqlite') {
            // SQLite tiene soporte limitado para CHECK constraints en ALTER TABLE
            // En este caso, la validación se manejará a nivel de aplicación
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql' || DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE user_quiz_responses DROP CONSTRAINT IF EXISTS chk_stoic_level");
        }
    }
};
