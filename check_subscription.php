<?php

/**
 * Script para verificar el estado de suscripción de un usuario
 * 
 * Uso: php check_subscription.php <user_id>
 */

require __DIR__ . '/vendor/autoload.php';

use App\Models\User;
use App\Models\Subscription;
use App\Models\UserQuizResponse;

// Cargar variables de entorno
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Configurar la conexión a base de datos
$capsule = new \Illuminate\Database\Capsule\Manager;
$capsule->addConnection([
    'driver' => 'mysql',
    'host' => $_ENV['DB_HOST'],
    'database' => $_ENV['DB_DATABASE'],
    'username' => $_ENV['DB_USERNAME'],
    'password' => $_ENV['DB_PASSWORD'],
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

// Obtener ID de usuario de los argumentos
$userId = $argv[1] ?? null;

if (!$userId) {
    echo "❌ Error: Debes proporcionar un ID de usuario\n";
    echo "Uso: php check_subscription.php <user_id>\n";
    exit(1);
}

try {
    // Buscar usuario
    $user = User::find($userId);
    
    if (!$user) {
        echo "❌ Error: Usuario no encontrado con ID: {$userId}\n";
        exit(1);
    }
    
    echo "\n=== INFORMACIÓN DEL USUARIO ===\n";
    echo "ID: {$user->id}\n";
    echo "Nombre: {$user->nombre} {$user->apellidos}\n";
    echo "Email: {$user->email}\n";
    echo "Email Verificado: " . ($user->email_verificado ? '✅ Sí' : '❌ No') . "\n";
    echo "Quiz Completado: " . ($user->quiz_completed ? '✅ Sí' : '❌ No') . "\n\n";
    
    // Verificar quiz
    $quizResponse = UserQuizResponse::where('user_id', $userId)->first();
    if ($quizResponse) {
        echo "=== RESPUESTAS DEL QUIZ ===\n";
        echo "Personalidad: {$quizResponse->personality_type}\n";
        echo "Intereses: {$quizResponse->interests}\n";
        echo "Nivel de Experiencia: {$quizResponse->experience_level}\n";
        echo "Desafíos: {$quizResponse->main_challenges}\n\n";
    } else {
        echo "⚠️  No hay respuestas de quiz registradas\n\n";
    }
    
    // Verificar suscripciones
    $subscriptions = Subscription::where('user_id', $userId)->get();
    
    echo "=== SUSCRIPCIONES ===\n";
    if ($subscriptions->isEmpty()) {
        echo "❌ No tiene suscripciones registradas\n\n";
    } else {
        foreach ($subscriptions as $index => $subscription) {
            echo "Suscripción #" . ($index + 1) . ":\n";
            echo "  Plan: {$subscription->plan_name}\n";
            echo "  Estado: {$subscription->status}\n";
            echo "  Monto: \${$subscription->amount} {$subscription->currency}\n";
            echo "  Período actual: {$subscription->current_period_start} - {$subscription->current_period_end}\n";
            echo "  Fecha de fin: " . ($subscription->ends_at ?? 'N/A') . "\n";
            echo "  Es activa: " . ($subscription->isActive() ? '✅ Sí' : '❌ No') . "\n";
            echo "\n";
        }
    }
    
    // Verificar si tiene suscripción activa (usando el método del modelo)
    $hasActiveSubscription = $user->hasActiveSubscription();
    
    echo "=== VERIFICACIÓN PARA FRASES PERSONALIZADAS ===\n";
    echo "Quiz Completado: " . ($user->quiz_completed ? '✅ Sí' : '❌ No') . "\n";
    echo "Suscripción Activa: " . ($hasActiveSubscription ? '✅ Sí' : '❌ No') . "\n\n";
    
    if ($user->quiz_completed && $hasActiveSubscription) {
        echo "✅ El usuario CUMPLE los requisitos para recibir frases personalizadas\n";
    } else {
        echo "❌ El usuario NO cumple los requisitos para recibir frases personalizadas\n";
        if (!$user->quiz_completed) {
            echo "   - Falta completar el quiz\n";
        }
        if (!$hasActiveSubscription) {
            echo "   - Falta tener una suscripción activa\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
