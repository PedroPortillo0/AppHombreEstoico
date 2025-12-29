<?php

/**
 * Script para probar la obtención de frase diaria de un usuario
 */

require __DIR__ . '/vendor/autoload.php';

use App\Models\User;
use App\Application\UseCases\GetDailyQuote;
use App\Infrastructure\Persistence\EloquentDailyQuoteRepository;
use App\Application\UseCases\GeneratePersonalizedQuoteExplanation;
use App\Infrastructure\Persistence\EloquentUserRepository;
use App\Infrastructure\Services\OpenAIService;

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
$userId = $argv[1] ?? 'd14848d6-2508-48bd-b1da-ebc5e3ffeec5';

try {
    echo "\n=== PROBANDO OBTENCIÓN DE FRASE DIARIA ===\n";
    echo "User ID: {$userId}\n\n";
    
    // Verificar usuario
    $user = \App\Models\User::find($userId);
    if (!$user) {
        echo "❌ Usuario no encontrado\n";
        exit(1);
    }
    
    echo "Usuario: {$user->nombre} {$user->apellidos}\n";
    echo "Quiz completado: " . ($user->quiz_completed ? 'Sí' : 'No') . "\n";
    echo "Tiene suscripción activa: " . ($user->hasActiveSubscription() ? 'Sí' : 'No') . "\n\n";
    
    // Crear instancias de los servicios
    $dailyQuoteRepository = new EloquentDailyQuoteRepository();
    $userRepository = new EloquentUserRepository();
    $openAIService = new OpenAIService();
    $personalizeQuote = new GeneratePersonalizedQuoteExplanation($openAIService);
    
    // Ejecutar caso de uso
    $getDailyQuote = new GetDailyQuote($dailyQuoteRepository, $personalizeQuote, $userRepository);
    
    echo "=== EJECUTANDO GetDailyQuote::execute() ===\n";
    $result = $getDailyQuote->execute(includeDetail: false, userId: $userId);
    
    echo "\n=== RESULTADO ===\n";
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    
    if ($result['success'] && isset($result['data']['is_personalized'])) {
        if ($result['data']['is_personalized']) {
            echo "\n✅ ÉXITO: Se generó una frase personalizada\n";
        } else {
            echo "\n❌ PROBLEMA: NO se generó una frase personalizada\n";
            echo "Revisa los logs para ver si hubo algún error en la generación con IA\n";
        }
    }
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
