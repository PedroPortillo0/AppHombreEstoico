<?php

namespace App\Http\Controllers;

use App\Application\UseCases\LoginWithGoogle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Exception;

class GoogleAuthController extends Controller
{
    public function __construct(
        private LoginWithGoogle $loginWithGoogle
    ) {}

    /**
     * Redirige al usuario a Google para autenticación
     */
    public function redirectToGoogle(): JsonResponse
    {
        try {
            $url = Socialite::driver('google')
                ->stateless()
                ->redirect()
                ->getTargetUrl();

            return response()->json([
                'success' => true,
                'message' => 'URL de autenticación generada',
                'data' => [
                    'url' => $url
                ]
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar URL de autenticación: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Maneja el callback de Google después de la autenticación
     * Redirige al deep link de la app móvil
     * Captura TODOS los errores (incluyendo 500) y redirige al deep link de error
     */
    public function handleGoogleCallback(Request $request): RedirectResponse
    {
        try {
            // Obtener información del usuario de Google
            // Esto puede lanzar excepciones de Socialite si hay problemas con OAuth
            try {
                $googleUser = Socialite::driver('google')
                    ->stateless()
                    ->user();
            } catch (\Laravel\Socialite\Two\InvalidStateException $e) {
                throw new Exception('Sesión de autenticación inválida. Por favor, intenta de nuevo.');
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                throw new Exception('Error al comunicarse con Google. Por favor, intenta de nuevo.');
            } catch (\GuzzleHttp\Exception\ServerException $e) {
                throw new Exception('Error en el servidor de Google. Por favor, intenta más tarde.');
            }

            // Validar que el usuario de Google no sea null
            if (!$googleUser) {
                throw new Exception('No se pudo obtener información del usuario de Google');
            }

            // Validar que los datos requeridos no sean null
            $googleId = $googleUser->getId();
            $email = $googleUser->getEmail();

            if (empty($googleId)) {
                throw new Exception('Google ID no disponible');
            }

            if (empty($email)) {
                throw new Exception('Email de Google no disponible');
            }

            // Validar formato del email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Email de Google con formato inválido');
            }

            // Preparar datos del usuario
            $googleUserData = [
                'id' => $googleId,
                'email' => $email,
                'name' => $googleUser->getName() ?? '',
                'avatar' => $googleUser->getAvatar()
            ];

            // Ejecutar caso de uso de login/registro
            $result = $this->loginWithGoogle->execute($googleUserData);

            // Si hay error, redirigir a deep link de error
            if (!$result['success']) {
                Log::error('Error en callback de Google', [
                    'result' => $result,
                    'google_data' => $googleUserData
                ]);
                
                $errorMessage = $this->formatErrorMessage($result['message'] ?? 'Error en autenticación');
                return $this->redirectToErrorDeepLink($errorMessage, 500);
            }

            // Validar que la respuesta tenga la estructura esperada
            if (!isset($result['data']['user']) || !isset($result['data']['token'])) {
                Log::error('Estructura de respuesta inválida en callback de Google', [
                    'result' => $result
                ]);
                throw new Exception('Respuesta del servidor inválida');
            }

            // Extraer datos del usuario y token
            $userData = $result['data']['user'];
            $token = $result['data']['token'];
            
            // Validar que el token no esté vacío
            if (empty($token)) {
                throw new Exception('Token de autenticación no generado');
            }

            // Validar que los datos del usuario estén presentes
            if (empty($userData['id']) || empty($userData['email'])) {
                throw new Exception('Datos del usuario incompletos');
            }
            
            $userId = $userData['id'];
            $nombre = urlencode($userData['nombre'] ?? '');
            $apellidos = urlencode($userData['apellidos'] ?? '');
            $email = urlencode($userData['email']);
            $quizCompleted = $userData['quizCompleted'] ?? false;
            $isNewUser = !$quizCompleted;

            // Construir deep link con todos los parámetros
            $deepLink = sprintf(
                "estoico://auth/success?token=%s&userId=%s&nombre=%s&apellidos=%s&email=%s&isNewUser=%s",
                urlencode($token),
                urlencode($userId),
                $nombre,
                $apellidos,
                $email,
                $isNewUser ? 'true' : 'false'
            );

            // Redirigir al deep link de la app móvil
            return redirect($deepLink, 302);

        } catch (\Throwable $e) {
            // Capturar TODOS los tipos de errores (Exception, Error, etc.)
            Log::error('Excepción en handleGoogleCallback', [
                'error' => $e->getMessage(),
                'type' => get_class($e),
                'trace' => $e->getTraceAsString()
            ]);
            
            $errorMessage = $this->formatErrorMessage($e->getMessage());
            return $this->redirectToErrorDeepLink($errorMessage, 500);
        }
    }

    /**
     * Formatea el mensaje de error para que sea más amigable al usuario
     */
    private function formatErrorMessage(string $errorMessage): string
    {
        $errorLower = strtolower($errorMessage);
        
        // Usuario duplicado
        if (strpos($errorLower, 'duplicate') !== false || 
            strpos($errorLower, 'already exists') !== false ||
            strpos($errorLower, 'ya está registrado') !== false) {
            return 'El usuario ya existe. Por favor, inicia sesión.';
        }
        
        // Error de base de datos
        if (strpos($errorLower, 'database') !== false || 
            strpos($errorLower, 'connection') !== false ||
            strpos($errorLower, 'sql') !== false) {
            return 'Error de conexión con la base de datos. Por favor, intenta de nuevo.';
        }
        
        // Error de validación
        if (strpos($errorLower, 'validation') !== false || 
            strpos($errorLower, 'invalid') !== false ||
            strpos($errorLower, 'incompletos') !== false) {
            return 'Datos inválidos recibidos de Google.';
        }
        
        // Error de token JWT
        if (strpos($errorLower, 'token') !== false || 
            strpos($errorLower, 'jwt') !== false) {
            return 'Error al generar el token de autenticación. Por favor, intenta de nuevo.';
        }
        
        // Error de Google OAuth
        if (strpos($errorLower, 'google') !== false || 
            strpos($errorLower, 'oauth') !== false ||
            strpos($errorLower, 'socialite') !== false) {
            return 'Error al autenticar con Google. Por favor, intenta de nuevo.';
        }
        
        // Error genérico
        return 'Error al procesar la autenticación. Por favor, intenta de nuevo.';
    }

    /**
     * Redirige al deep link de error con todos los parámetros requeridos
     */
    private function redirectToErrorDeepLink(string $errorMessage, int $statusCode = 500): RedirectResponse
    {
        $encodedError = urlencode($errorMessage);
        $encodedMessage = urlencode($errorMessage);
        
        // Construir deep link con todos los parámetros requeridos por el frontend
        $deepLink = sprintf(
            "estoico://auth/error?error=%s&status_code=%d&message=%s",
            $encodedError,
            $statusCode,
            $encodedMessage
        );
        
        return redirect($deepLink, 302);
    }

    /**
     * Maneja login con Google desde aplicaciones móviles o SPA
     * que ya tienen el token de Google
     */
    public function loginWithGoogleToken(Request $request): JsonResponse
    {
        try {
            // Validar que se reciba el token
            if (!$request->has('access_token')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token de acceso de Google requerido'
                ], 400);
            }

            $accessToken = $request->input('access_token');
            
            // Validar que el token no esté vacío
            if (empty(trim($accessToken))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token de acceso de Google no puede estar vacío'
                ], 400);
            }

            // Obtener información del usuario usando el token
            $googleUser = Socialite::driver('google')
                ->stateless()
                ->userFromToken($accessToken);

            // Validar que el usuario de Google no sea null
            if (!$googleUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo obtener información del usuario de Google'
                ], 400);
            }

            // Validar que los datos requeridos no sean null
            $googleId = $googleUser->getId();
            $email = $googleUser->getEmail();

            if (empty($googleId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Google ID no disponible'
                ], 400);
            }

            if (empty($email)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email de Google no disponible'
                ], 400);
            }

            // Validar formato del email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email de Google con formato inválido'
                ], 400);
            }

            // Preparar datos del usuario
            $googleUserData = [
                'id' => $googleId,
                'email' => $email,
                'name' => $googleUser->getName() ?? '',
                'avatar' => $googleUser->getAvatar()
            ];

            // Ejecutar caso de uso de login/registro
            $result = $this->loginWithGoogle->execute($googleUserData);

            return response()->json($result, $result['success'] ? 200 : 400);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al autenticar con token de Google: ' . $e->getMessage()
            ], 500);
        }
    }
}

