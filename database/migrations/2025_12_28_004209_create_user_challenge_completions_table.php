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
        Schema::create('user_challenge_completions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('user_id');
            $table->string('challenge_id'); // ID del desafío generado por RAG
            $table->string('challenge_title')->nullable();
            $table->text('challenge_description')->nullable();
            $table->integer('points')->default(1); // Cada actividad vale 1 punto
            $table->timestamp('completed_at');
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('user_id');
            $table->index('completed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_challenge_completions');
    }
};
