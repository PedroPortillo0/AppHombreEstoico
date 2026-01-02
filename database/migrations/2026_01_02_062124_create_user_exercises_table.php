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
        Schema::create('user_exercises', function (Blueprint $table) {
            $table->string('id', 255)->primary();
            $table->string('user_id', 255);
            $table->string('exercise_name', 255);
            $table->string('exercise_level', 50);
            $table->text('objective');
            $table->text('instructions');
            $table->string('duration', 100);
            $table->text('reflection');
            $table->string('source', 500)->nullable();
            $table->enum('status', ['pending', 'in_progress', 'completed'])->default('pending');
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            // Índices
            $table->index('user_id', 'idx_user_id');
            $table->index(['user_id', 'status'], 'idx_user_status');

            // Foreign key
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
        });

        // Agregar el default UUID() para el campo id
        DB::statement("ALTER TABLE user_exercises MODIFY COLUMN id VARCHAR(255) DEFAULT (UUID())");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_exercises');
    }
};
