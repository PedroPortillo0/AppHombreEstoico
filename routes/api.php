<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\DailyQuoteController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\DiarioController;
use App\Http\Controllers\ChallengeController;
use App\Http\Controllers\SubscriptionController;

// ========================================
// RUTAS PÚBLICAS (Sin autenticación)
// ========================================

// Ruta de prueba
Route::get('/test', function () {
    return response()->json([
        'message' => 'API funcionando correctamente',
        'timestamp' => now()
    ]);
});

// Rutas públicas de usuarios
Route::prefix('users')->group(function () {
    // Registro de usuario
    Route::post('/register', [UserController::class, 'register']);
    
    // Login de usuario
    Route::post('/login', [UserController::class, 'login']);
    
    // Verificación de email (método original con token)
    Route::get('/verifyemail', [UserController::class, 'verifyEmail']);
    
    // Verificación de email con código de 6 dígitos
    Route::post('/verifyemailcode', [UserController::class, 'verifyEmailWithCode']);
    
    // Reset de contraseña (sin autenticación)
    Route::post('/request-password-reset', [UserController::class, 'requestPasswordReset']);
    Route::patch('/reset-password', [UserController::class, 'resetPassword']);
});

// ========================================
// RUTAS PROTEGIDAS (Requieren JWT)
// ========================================

// Rutas protegidas de usuarios
Route::prefix('users')->middleware('jwt.auth')->group(function () {
    // Información del usuario autenticado
    Route::get('/me', [UserController::class, 'me']);
    
    // Actualizar información del quiz del usuario autenticado
    Route::patch('/quiz-info', [UserController::class, 'updateQuizInfo']);
    
    // Obtener usuarios (requiere autenticación)
    Route::get('/', [UserController::class, 'getAllUsers']);
    Route::get('/{id}', [UserController::class, 'getUser']);
    
    // Eliminar usuario (requiere autenticación)
    Route::delete('/{id}', [UserController::class, 'deleteUser']);
});

// ========================================
// RUTAS DE AUTENTICACIÓN CON GOOGLE
// ========================================

Route::prefix('auth/google')->group(function () {
    // Obtener URL de autenticación de Google
    Route::get('/redirect', [GoogleAuthController::class, 'redirectToGoogle']);
    
    // Callback de Google después de autenticación
    Route::get('/callback', [GoogleAuthController::class, 'handleGoogleCallback']);
    
    // Login con token de Google (para apps móviles/SPA)
    Route::post('/token', [GoogleAuthController::class, 'loginWithGoogleToken']);
});



// ========================================
// RUTAS DE QUIZ
// ========================================

// Rutas públicas del quiz
Route::prefix('quiz')->group(function () {
    // Obtener opciones disponibles para el quiz
    Route::get('/options', [QuizController::class, 'getOptions']);
});

// Rutas protegidas del quiz (requieren JWT)
Route::prefix('quiz')->middleware('jwt.auth')->group(function () {
    // Enviar respuestas del quiz
    Route::post('/submit', [QuizController::class, 'submit']);
    
    // Obtener quiz del usuario
    Route::get('/my-quiz', [QuizController::class, 'getUserQuizResponse']);
});

// ========================================
// RUTAS DE FRASES DIARIAS (Requieren autenticación JWT)
// ========================================
// Si el usuario tiene quiz completo → recibe frase personalizada con IA
// Si el usuario NO tiene quiz completo → recibe frase normal

Route::prefix('daily-quote')->middleware('jwt.auth')->group(function () {
    // Obtener frase del día (simple para dashboard)
    // Si tiene quiz completo → frase personalizada
    // Si no tiene quiz → frase normal
    Route::get('/', [DailyQuoteController::class, 'getDailyQuote']);
    
    // Obtener detalle completo de la frase del día
    // Si tiene quiz completo → frase personalizada
    // Si no tiene quiz → frase normal
    Route::get('/detail', [DailyQuoteController::class, 'getDailyQuoteDetail']);
    
    // Obtener todas las frases (para testing/admin)
    Route::get('/all', [DailyQuoteController::class, 'getAllQuotes']);
});


// ========================================
// RUTAS DE DIARIO (Reflexiones)
// ========================================
// Guardar reflexiones. Requiere autenticación JWT.
Route::prefix('diario')->middleware('jwt.auth')->group(function () {
    // Obtener todas las reflexiones del usuario
    Route::get('/all', [DiarioController::class, 'all']);
    // Obtener reflexiones del día (opcional ?all=1 para todas, ?date=YYYY-MM-DD para fecha específica)
    Route::get('/', [DiarioController::class, 'show']);
    // Guardar o actualizar (crear) la reflexión para la fecha actual
    // Obtener todas las reflexiones del usuario (útil para Postman): /api/diario/all
    Route::get('/all', [DiarioController::class, 'all']);
    // Guardar o actualizar (crear) las reflexiones para una fecha dada
    Route::post('/', [DiarioController::class, 'store']);

    // Actualizar una reflexión por id (solo del usuario autenticado)
    Route::patch('/{id}', [DiarioController::class, 'update']);

    // Eliminar una reflexión por id (solo del usuario autenticado)
    Route::delete('/{id}', [DiarioController::class, 'destroy']);
});

// ========================================
// RUTAS DE DESAFÍOS ESTOICOS
// ========================================
// Sistema de puntos y niveles dinámicos. Requiere autenticación JWT.
Route::prefix('challenges')->middleware('jwt.auth')->group(function () {
    // Registrar que un desafío fue completado
    Route::post('/complete', [ChallengeController::class, 'complete']);
    
    // Obtener progreso del usuario (puntos, nivel actual, siguiente nivel)
    Route::get('/progress', [ChallengeController::class, 'getProgress']);
    
    // Obtener historial de actividades completadas
    Route::get('/history', [ChallengeController::class, 'getHistory']);
});

// RUTAS DE SUSCRIPCIONES (Requieren autenticación JWT)
// ========================================
Route::prefix('subscriptions')->middleware('jwt.auth')->group(function () {
    // Crear nueva suscripción
    Route::post('/', [SubscriptionController::class, 'subscribe']);
    
    // Obtener estado de la suscripción actual
    Route::get('/status', [SubscriptionController::class, 'status']);
    
    // Verificar si el usuario puede acceder a frases personalizadas
    Route::get('/check-personalized-access', [SubscriptionController::class, 'checkPersonalizedAccess']);
    
    // Listar todas las suscripciones del usuario
    Route::get('/', [SubscriptionController::class, 'index']);
    
    // Cancelar suscripción activa
    Route::delete('/', [SubscriptionController::class, 'cancel']);
});

// ========================================
// WEBHOOK DE OPENPAY (Sin autenticación - OpenPay lo llama)
// ========================================
Route::post('/subscriptions/webhook', [SubscriptionController::class, 'webhook']
);