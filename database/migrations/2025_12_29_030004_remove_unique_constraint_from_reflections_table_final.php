<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('reflections')) {
            return;
        }

        // Eliminar la restricción única usando SQL directo
        // Buscar y eliminar el índice único reflections_user_id_date_unique
        try {
            // Intentar eliminar con el nombre estándar
            DB::statement('ALTER TABLE reflections DROP INDEX reflections_user_id_date_unique');
        } catch (\Throwable $e) {
            // Si falla, buscar el nombre real del índice único
            try {
                $database = DB::connection()->getDatabaseName();
                $indexes = DB::select(
                    "SELECT CONSTRAINT_NAME 
                    FROM information_schema.TABLE_CONSTRAINTS 
                    WHERE TABLE_SCHEMA = ? 
                    AND TABLE_NAME = 'reflections' 
                    AND CONSTRAINT_TYPE = 'UNIQUE'",
                    [$database]
                );

                foreach ($indexes as $index) {
                    $constraintName = $index->CONSTRAINT_NAME;
                    
                    // Verificar si el índice contiene user_id y date
                    $columns = DB::select(
                        "SELECT COLUMN_NAME 
                        FROM information_schema.KEY_COLUMN_USAGE 
                        WHERE TABLE_SCHEMA = ? 
                        AND TABLE_NAME = 'reflections' 
                        AND CONSTRAINT_NAME = ?",
                        [$database, $constraintName]
                    );

                    $columnNames = array_map(fn($col) => $col->COLUMN_NAME, $columns);
                    
                    if (in_array('user_id', $columnNames) && in_array('date', $columnNames)) {
                        DB::statement("ALTER TABLE reflections DROP INDEX `{$constraintName}`");
                        break;
                    }
                }
            } catch (\Throwable $e2) {
                // Si todo falla, intentar con SHOW INDEX (MySQL específico)
                try {
                    $indexes = DB::select("SHOW INDEX FROM reflections WHERE Column_name IN ('user_id', 'date')");
                    
                    foreach ($indexes as $index) {
                        if ($index->Non_unique == 0) { // Es un índice único
                            DB::statement("ALTER TABLE reflections DROP INDEX `{$index->Key_name}`");
                            break;
                        }
                    }
                } catch (\Throwable $e3) {
                    // Log el error pero no fallar la migración
                    \Log::warning("No se pudo eliminar la restricción única de reflections: " . $e3->getMessage());
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('reflections')) {
            return;
        }

        // Recrear la restricción única si se necesita revertir
        try {
            Schema::table('reflections', function (Blueprint $table) {
                $table->unique(['user_id', 'date'], 'reflections_user_id_date_unique');
            });
        } catch (\Throwable $e) {
            // Ignorar si ya existe
        }
    }
};
