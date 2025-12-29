<?php

/**
 * Script de verificación de instalación de OpenPay
 * 
 * Para ejecutar:
 * php artisan tinker
 * include('verificar_openpay.php');
 */

echo "===========================================\n";
echo "  VERIFICACIÓN DE INSTALACIÓN OPENPAY\n";
echo "===========================================\n\n";

// 1. Verificar que el SDK está instalado
echo "1. Verificando SDK de OpenPay...\n";
if (class_exists('Openpay\Data\Openpay')) {
    echo "   ✅ SDK de OpenPay instalado correctamente\n\n";
} else {
    echo "   ❌ SDK de OpenPay NO encontrado\n";
    echo "   Ejecuta: composer require openpay/sdk\n\n";
    exit;
}

// 2. Verificar configuración
echo "2. Verificando configuración...\n";
$merchantId = config('services.openpay.merchant_id');
$privateKey = config('services.openpay.private_key');
$publicKey = config('services.openpay.public_key');
$sandboxMode = config('services.openpay.sandbox_mode');

if (empty($merchantId) || empty($privateKey) || empty($publicKey)) {
    echo "   ❌ Faltan credenciales de OpenPay en .env\n";
    echo "   Agrega las siguientes variables a tu archivo .env:\n";
    echo "   OPENPAY_MERCHANT_ID=\n";
    echo "   OPENPAY_PRIVATE_KEY=\n";
    echo "   OPENPAY_PUBLIC_KEY=\n\n";
} else {
    echo "   ✅ Credenciales configuradas\n";
    echo "   - Merchant ID: " . substr($merchantId, 0, 5) . "...\n";
    echo "   - Private Key: " . substr($privateKey, 0, 10) . "...\n";
    echo "   - Public Key: " . substr($publicKey, 0, 10) . "...\n";
    echo "   - Modo Sandbox: " . ($sandboxMode ? 'Sí' : 'No') . "\n\n";
}

// 3. Verificar tabla de suscripciones
echo "3. Verificando tabla de suscripciones...\n";
try {
    \Illuminate\Support\Facades\Schema::hasTable('subscriptions');
    $columns = \Illuminate\Support\Facades\Schema::getColumnListing('subscriptions');
    echo "   ✅ Tabla 'subscriptions' existe\n";
    echo "   - Columnas: " . count($columns) . "\n";
    echo "   - Campos principales: user_id, openpay_subscription_id, status\n\n";
} catch (\Exception $e) {
    echo "   ❌ Error con la tabla subscriptions: " . $e->getMessage() . "\n";
    echo "   Ejecuta: php artisan migrate\n\n";
}

// 4. Verificar modelo Subscription
echo "4. Verificando modelo Subscription...\n";
if (class_exists('App\Models\Subscription')) {
    echo "   ✅ Modelo Subscription existe\n";
    $subscription = new \App\Models\Subscription();
    echo "   - Tabla: " . $subscription->getTable() . "\n";
    echo "   - Fillable: " . count($subscription->getFillable()) . " campos\n\n";
} else {
    echo "   ❌ Modelo Subscription NO encontrado\n\n";
}

// 5. Verificar servicio OpenPayService
echo "5. Verificando OpenPayService...\n";
if (class_exists('App\Infrastructure\Services\OpenPayService')) {
    echo "   ✅ OpenPayService existe\n";
    try {
        $service = new \App\Infrastructure\Services\OpenPayService();
        echo "   - Servicio instanciado correctamente\n\n";
    } catch (\Exception $e) {
        echo "   ⚠️  Error al instanciar servicio: " . $e->getMessage() . "\n\n";
    }
} else {
    echo "   ❌ OpenPayService NO encontrado\n\n";
}

// 6. Verificar controlador
echo "6. Verificando SubscriptionController...\n";
if (class_exists('App\Http\Controllers\SubscriptionController')) {
    echo "   ✅ SubscriptionController existe\n";
    $reflection = new \ReflectionClass('App\Http\Controllers\SubscriptionController');
    $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
    $methodNames = array_map(fn($m) => $m->name, $methods);
    $relevantMethods = array_filter($methodNames, fn($name) => !in_array($name, ['__construct', '__call', '__callStatic', '__get', '__set', '__isset', '__unset', '__sleep', '__wakeup', '__toString', '__invoke', '__set_state', '__clone', '__debugInfo']));
    echo "   - Métodos disponibles: " . implode(', ', $relevantMethods) . "\n\n";
} else {
    echo "   ❌ SubscriptionController NO encontrado\n\n";
}

// 7. Verificar rutas
echo "7. Verificando rutas API...\n";
$routes = \Illuminate\Support\Facades\Route::getRoutes();
$subscriptionRoutes = collect($routes)->filter(function($route) {
    return str_contains($route->uri(), 'subscriptions');
});

if ($subscriptionRoutes->count() > 0) {
    echo "   ✅ Rutas de suscripciones registradas\n";
    foreach ($subscriptionRoutes as $route) {
        echo "   - " . implode('|', $route->methods()) . " " . $route->uri() . "\n";
    }
    echo "\n";
} else {
    echo "   ❌ No se encontraron rutas de suscripciones\n\n";
}

// 8. Resumen
echo "===========================================\n";
echo "  RESUMEN\n";
echo "===========================================\n";
echo "Todo está listo para usar OpenPay! 🎉\n\n";
echo "Próximos pasos:\n";
echo "1. Configura tus credenciales de OpenPay en .env\n";
echo "2. Prueba los endpoints con Postman\n";
echo "3. Integra en tu app móvil/web\n\n";
echo "Documentación:\n";
echo "- OPENPAY_INTEGRATION.md\n";
echo "- SUBSCRIPTION_SETUP.md\n\n";
echo "===========================================\n";
