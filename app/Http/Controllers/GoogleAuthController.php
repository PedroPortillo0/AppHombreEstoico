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
            // Configurar redirect_uri para usar el dominio de producción
            $redirectUri = env('GOOGLE_REDIRECT_URI', 'https://web.estoico.app/api/auth/google/callback');
            
            $url = Socialite::driver('google')
                ->stateless()
                ->redirectUrl($redirectUri)
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
     * Redirige al deep link de la app móvil con los datos de autenticación
     */
    public function handleGoogleCallback(Request $request): RedirectResponse|JsonResponse
    {
        // Verificar si Google devolvió un error
        if ($request->has('error')) {
            $error = $request->query('error');
            $errorDescription = $request->query('error_description', 'Error desconocido de Google');
            
            Log::error('Error de Google OAuth en callback', [
                'error' => $error,
                'error_description' => $errorDescription
            ]);
            
            $errorMessage = $this->formatGoogleOAuthError($error, $errorDescription);
            return $this->redirectToErrorDeepLink($errorMessage, 400);
        }

        // Verificar que el código de autorización esté presente
        if (!$request->has('code')) {
            Log::error('Callback de Google sin código de autorización', [
                'query_params' => $request->query(),
                'full_url' => $request->fullUrl()
            ]);
            
            $errorMessage = 'No se recibió el código de autorización de Google. Por favor, intenta de nuevo.';
            return $this->redirectToErrorDeepLink($errorMessage, 400);
        }

        try {
            // Configurar redirect_uri para usar el dominio de producción
            $redirectUri = env('GOOGLE_REDIRECT_URI', 'https://web.estoico.app/api/auth/google/callback');
            
            // Obtener información del usuario de Google
            // Esto puede lanzar excepciones de Socialite si hay problemas con OAuth
            try {
                $googleUser = Socialite::driver('google')
                    ->stateless()
                    ->redirectUrl($redirectUri)
                    ->user();
            } catch (\Laravel\Socialite\Two\InvalidStateException $e) {
                Log::error('InvalidStateException en Google OAuth', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                $errorMessage = 'Sesión de autenticación inválida. Por favor, intenta de nuevo.';
                return $this->redirectToErrorDeepLink($errorMessage, 400);
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                $response = $e->getResponse();
                $responseBody = $response ? $response->getBody()->getContents() : null;
                $statusCode = $response ? $response->getStatusCode() : null;
                
                Log::error('ClientException en Google OAuth', [
                    'error' => $e->getMessage(),
                    'status_code' => $statusCode,
                    'response' => $responseBody,
                    'trace' => $e->getTraceAsString()
                ]);
                
                // Proporcionar mensaje más específico basado en el código de estado
                if ($statusCode === 400) {
                    $message = 'Solicitud inválida a Google. Verifica la configuración de OAuth.';
                } elseif ($statusCode === 401) {
                    $message = 'Credenciales de Google OAuth inválidas. Verifica CLIENT_ID y CLIENT_SECRET.';
                } else {
                    $message = 'Error al comunicarse con Google (código ' . $statusCode . '). Por favor, intenta de nuevo.';
                }
                
                return $this->redirectToErrorDeepLink($message, $statusCode ?? 500);
            } catch (\GuzzleHttp\Exception\ServerException $e) {
                Log::error('ServerException en Google OAuth', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                $errorMessage = 'Error en el servidor de Google. Por favor, intenta más tarde.';
                return $this->redirectToErrorDeepLink($errorMessage, 500);
            } catch (\Exception $e) {
                Log::error('Excepción al obtener usuario de Google', [
                    'error' => $e->getMessage(),
                    'class' => get_class($e),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
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

            // Si hay error, retornar con mensaje formateado
            if (!$result['success']) {
                Log::error('Error en callback de Google', [
                    'result' => $result,
                    'google_data' => $googleUserData,
                    'original_message' => $result['message'] ?? 'Sin mensaje'
                ]);
                
                // Usar el mensaje formateado
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
            $isNewUser = $result['data']['is_new_user'] ?? false;
            
            // Validar que el token no esté vacío
            if (empty($token)) {
                throw new Exception('Token de autenticación no generado');
            }

            // Validar que los datos del usuario estén presentes
            if (empty($userData['id']) || empty($userData['email'])) {
                throw new Exception('Datos del usuario incompletos');
            }

            // Redirigir al deep link de éxito
            return $this->redirectToSuccessDeepLink(
                $token,
                $userData['id'],
                $userData['nombre'] ?? '',
                $userData['apellidos'] ?? '',
                $userData['email'] ?? '',
                $isNewUser
            );

        } catch (\Throwable $e) {
            // Capturar TODOS los tipos de errores (Exception, Error, etc.)
            Log::error('Excepción en handleGoogleCallback', [
                'error' => $e->getMessage(),
                'type' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $errorMessage = $this->formatErrorMessage($e->getMessage());
            return $this->redirectToErrorDeepLink($errorMessage, 500);
        }
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

            // Si hay error, retornar con mensaje formateado
            if (!$result['success']) {
                Log::error('Error en loginWithGoogleToken', [
                    'result' => $result,
                    'google_data' => $googleUserData
                ]);
                
                $errorMessage = $this->formatErrorMessage($result['message'] ?? 'Error en autenticación');
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage
                ], 400);
            }

            // Validar que la respuesta tenga la estructura esperada
            if (!isset($result['data']['user']) || !isset($result['data']['token'])) {
                Log::error('Estructura de respuesta inválida en loginWithGoogleToken', [
                    'result' => $result
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Respuesta del servidor inválida'
                ], 500);
            }

            return response()->json($result, 200);

        } catch (\Throwable $e) {
            Log::error('Excepción en loginWithGoogleToken', [
                'error' => $e->getMessage(),
                'type' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $errorMessage = $this->formatErrorMessage($e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $errorMessage
            ], 500);
        }
    }

    /**
     * Formatea el mensaje de error para que sea más amigable al usuario
     * Preserva el mensaje original cuando contiene información útil
     */
    private function formatErrorMessage(string $errorMessage): string
    {
        $errorLower = strtolower($errorMessage);
        
        // Usuario duplicado - verificar primero porque es más específico
        if (strpos($errorLower, 'duplicate') !== false || 
            strpos($errorLower, 'already exists') !== false ||
            strpos($errorLower, 'ya está registrado') !== false ||
            strpos($errorLower, 'ya existe') !== false) {
            return 'El usuario ya existe con este email. Por favor, inicia sesión.';
        }
        
        // Error de base de datos - verificar antes de "google" genérico
        if (strpos($errorLower, 'database') !== false || 
            strpos($errorLower, 'connection') !== false ||
            strpos($errorLower, 'sql') !== false ||
            strpos($errorLower, 'query') !== false) {
            return 'Error de conexión con la base de datos. Por favor, intenta de nuevo.';
        }
        
        // Error al guardar usuario - mensaje específico
        if (strpos($errorLower, 'error al guardar usuario') !== false ||
            strpos($errorLower, 'error al crear cuenta') !== false ||
            strpos($errorLower, 'error al crear usuario') !== false) {
            // Extraer el mensaje específico si está disponible
            if (strpos($errorLower, 'email') !== false && strpos($errorLower, 'registrado') !== false) {
                return 'El email ya está registrado. Por favor, inicia sesión.';
            }
            if (strpos($errorLower, 'duplicate') !== false) {
                return 'El usuario ya existe. Por favor, inicia sesión.';
            }
            // Si no hay más información, usar mensaje descriptivo
            return 'Error al crear la cuenta. Por favor, intenta de nuevo.';
        }
        
        // Error de validación
        if (strpos($errorLower, 'validation') !== false || 
            strpos($errorLower, 'invalid') !== false ||
            strpos($errorLower, 'incompletos') !== false ||
            strpos($errorLower, 'formato inválido') !== false) {
            return 'Datos inválidos recibidos de Google. Por favor, intenta de nuevo.';
        }
        
        // Error de token JWT
        if (strpos($errorLower, 'token') !== false && 
            (strpos($errorLower, 'jwt') !== false || 
             strpos($errorLower, 'generar') !== false ||
             strpos($errorLower, 'no generado') !== false)) {
            return 'Error al generar el token de autenticación. Por favor, intenta de nuevo.';
        }
        
        // Error de Google OAuth - solo si es específicamente sobre OAuth/Socialite
        // NO convertir todos los errores que contengan "google" en genérico
        if ((strpos($errorLower, 'oauth') !== false || 
             strpos($errorLower, 'socialite') !== false ||
             strpos($errorLower, 'sesión de autenticación inválida') !== false ||
             strpos($errorLower, 'comunicarse con google') !== false) &&
            strpos($errorLower, 'error al crear') === false &&
            strpos($errorLower, 'error al guardar') === false) {
            return 'Error al autenticar con Google. Por favor, intenta de nuevo.';
        }
        
        // Si el mensaje contiene información útil, preservarlo
        // Solo usar mensaje genérico si realmente no hay información
        if (strlen($errorMessage) > 50 || 
            strpos($errorLower, 'error') === false) {
            // El mensaje parece tener información útil, usarlo directamente
            // Pero limpiarlo un poco si es muy técnico
            if (strpos($errorLower, 'sqlstate') !== false ||
                strpos($errorLower, 'pdoexception') !== false) {
                return 'Error de base de datos. Por favor, intenta de nuevo.';
            }
            // Preservar el mensaje original si parece útil
            return $errorMessage;
        }
        
        // Error genérico solo como último recurso
        return 'Error al procesar la autenticación. Por favor, intenta de nuevo.';
    }

    /**
     * Formatea errores específicos de Google OAuth
     */
    private function formatGoogleOAuthError(string $error, string $errorDescription): string
    {
        $errorLower = strtolower($error);
        
        if ($errorLower === 'access_denied') {
            return 'Acceso denegado. Por favor, acepta los permisos para continuar.';
        }
        
        if ($errorLower === 'invalid_request') {
            return 'Solicitud inválida. Por favor, intenta de nuevo.';
        }
        
        if ($errorLower === 'invalid_client') {
            return 'Error de configuración del servidor. Por favor, contacta al soporte.';
        }
        
        if ($errorLower === 'invalid_grant') {
            return 'Código de autorización inválido o expirado. Por favor, intenta de nuevo.';
        }
        
        if ($errorLower === 'unauthorized_client') {
            return 'Cliente no autorizado. Por favor, contacta al soporte.';
        }
        
        if ($errorLower === 'unsupported_response_type') {
            return 'Tipo de respuesta no soportado. Por favor, contacta al soporte.';
        }
        
        if ($errorLower === 'invalid_scope') {
            return 'Permisos inválidos solicitados. Por favor, contacta al soporte.';
        }
        
        // Usar la descripción si está disponible
        if (!empty($errorDescription)) {
            return $errorDescription;
        }
        
        return 'Error al autenticar con Google: ' . $error;
    }

    /**
     * Construye y redirige al deep link de éxito
     */
    private function redirectToSuccessDeepLink(
        string $token,
        string $userId,
        string $nombre,
        string $apellidos,
        string $email,
        bool $isNewUser
    ): RedirectResponse {
        $deepLink = 'estoico://auth/success?' . http_build_query([
            'token' => $token,
            'userId' => $userId,
            'nombre' => $nombre,
            'apellidos' => $apellidos,
            'email' => $email,
            'is_new_user' => $isNewUser ? 'true' : 'false'
        ]);

        Log::info('Redirigiendo a deep link de éxito', [
            'deep_link' => $deepLink,
            'user_id' => $userId,
            'is_new_user' => $isNewUser
        ]);

        return redirect($deepLink);
    }

    /**
     * Construye y redirige al deep link de error
     */
    private function redirectToErrorDeepLink(string $errorMessage, int $statusCode = 500): RedirectResponse
    {
        $deepLink = 'estoico://auth/error?' . http_build_query([
            'error' => $errorMessage,
            'message' => $errorMessage,
            'status_code' => (string)$statusCode,
            'statusCode' => (string)$statusCode
        ]);

        Log::warning('Redirigiendo a deep link de error', [
            'deep_link' => $deepLink,
            'error_message' => $errorMessage,
            'status_code' => $statusCode
        ]);

        return redirect($deepLink);
    }
}

