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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('user_id'); // UUID del usuario
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            
            // IDs de OpenPay
            $table->string('openpay_customer_id')->nullable();
            $table->string('openpay_subscription_id')->nullable();
            $table->string('openpay_plan_id')->nullable();
            $table->string('openpay_card_id')->nullable();
            
            // Información del plan
            $table->string('plan_name')->default('Premium');
            $table->decimal('amount', 10, 2)->default(99.99);
            $table->string('currency', 3)->default('MXN');
            $table->string('interval')->default('month'); // day, week, month, year
            
            // Estado de la suscripción
            $table->enum('status', [
                'active', 
                'cancelled', 
                'past_due', 
                'trial', 
                'unpaid'
            ])->default('active');
            
            // Fechas importantes
            $table->timestamp('trial_start')->nullable();
            $table->timestamp('trial_end')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            
            // Metadata adicional
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            
            // Índices
            $table->index('user_id');
            $table->index('openpay_customer_id');
            $table->index('openpay_subscription_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
