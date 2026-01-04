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
            // Verificar configuración de Google OAuth
            $clientId = config('services.google.client_id');
            $clientSecret = config('services.google.client_secret');
            $redirectUri = config('services.google.redirect');
            
            Log::info('Generando URL de autenticación de Google', [
                'has_client_id' => !empty($clientId),
                'has_client_secret' => !empty($clientSecret),
                'redirect_uri' => $redirectUri
            ]);
            
            if (empty($clientId) || empty($clientSecret)) {
                throw new Exception('Configuración de Google OAuth incompleta. Verifica GOOGLE_CLIENT_ID y GOOGLE_CLIENT_SECRET en .env');
            }
            
            if (empty($redirectUri)) {
                throw new Exception('GOOGLE_REDIRECT_URI no configurado en .env');
            }
            
            // #region agent log
            $debugLogPath = base_path('.cursor/debug.log');
            @file_put_contents($debugLogPath, json_encode([
                'sessionId' => 'debug-session',
                'runId' => 'run1',
                'hypothesisId' => 'A,B,D',
                'location' => 'GoogleAuthController.php:45',
                'message' => 'redirectToGoogle: Antes de generar URL',
                'data' => [
                    'redirect_uri' => $redirectUri,
                    'redirect_uri_length' => strlen($redirectUri),
                    'client_id' => substr($clientId, 0, 20) . '...'
                ],
                'timestamp' => time() * 1000
            ]) . "\n", FILE_APPEND | LOCK_EX);
            // #endregion
            
            // Construir la URL de autenticación con el redirect_uri explícito
            $url = Socialite::driver('google')
                ->stateless()
                ->redirectUrl($redirectUri) // Asegurar que use el redirect_uri correcto
                ->redirect()
                ->getTargetUrl();
            
            // #region agent log
            $debugLogPath = base_path('.cursor/debug.log');
            @file_put_contents($debugLogPath, json_encode([
                'sessionId' => 'debug-session',
                'runId' => 'run1',
                'hypothesisId' => 'A,B',
                'location' => 'GoogleAuthController.php:52',
                'message' => 'redirectToGoogle: Después de generar URL',
                'data' => [
                    'url_generada' => $url,
                    'url_contiene_redirect_uri' => strpos($url, urlencode($redirectUri)) !== false || strpos($url, $redirectUri) !== false,
                    'redirect_uri_en_url' => strpos($url, urlencode($redirectUri)) !== false ? 'urlencoded' : (strpos($url, $redirectUri) !== false ? 'plain' : 'not_found')
                ],
                'timestamp' => time() * 1000
            ]) . "\n", FILE_APPEND | LOCK_EX);
            // #endregion

            // Verificar que la URL generada contenga el redirect_uri correcto
            $urlContainsRedirect = strpos($url, urlencode($redirectUri)) !== false || 
                                   strpos($url, $redirectUri) !== false;
            
            Log::info('URL de autenticación generada', [
                'url' => $url,
                'redirect_uri_esperado' => $redirectUri,
                'url_contiene_redirect_uri' => $urlContainsRedirect,
                'url_length' => strlen($url)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'URL de autenticación generada',
                'data' => [
                    'url' => $url
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Error al generar URL de autenticación de Google', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
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
        // Log al inicio para verificar que el callback se está llamando
        $redirectUriConfig = config('services.google.redirect');
        $fullUrl = $request->fullUrl();
        $expectedCallbackUrl = url('/api/auth/google/callback');
        
        // #region agent log
        $debugLogPath = base_path('.cursor/debug.log');
        @file_put_contents($debugLogPath, json_encode([
            'sessionId' => 'debug-session',
            'runId' => 'run4',
            'hypothesisId' => 'ALL',
            'location' => 'GoogleAuthController.php:149',
            'message' => 'handleGoogleCallback: INICIO - Callback recibido',
            'data' => [
                'has_code' => $request->has('code'),
                'has_error' => $request->has('error'),
                'error' => $request->query('error'),
                'error_description' => $request->query('error_description'),
                'query_params' => $request->query(),
                'full_url' => $fullUrl
            ],
            'timestamp' => time() * 1000
        ]) . "\n", FILE_APPEND | LOCK_EX);
        // #endregion
        
        // #region agent log
        $debugLogPath = base_path('.cursor/debug.log');
        @file_put_contents($debugLogPath, json_encode([
            'sessionId' => 'debug-session',
            'runId' => 'run4',
            'hypothesisId' => 'ALL',
            'location' => 'GoogleAuthController.php:149',
            'message' => 'handleGoogleCallback: INICIO - Callback recibido',
            'data' => [
                'has_code' => $request->has('code'),
                'has_error' => $request->has('error'),
                'error' => $request->query('error'),
                'error_description' => $request->query('error_description'),
                'query_params' => $request->query(),
                'full_url' => $fullUrl
            ],
            'timestamp' => time() * 1000
        ]) . "\n", FILE_APPEND | LOCK_EX);
        // #endregion
        
        Log::info('Google OAuth callback recibido', [
            'query_params' => $request->query(),
            'has_code' => $request->has('code'),
            'has_error' => $request->has('error'),
            'error' => $request->query('error'),
            'error_description' => $request->query('error_description'),
            'full_url' => $fullUrl,
            'redirect_uri_config' => $redirectUriConfig,
            'expected_callback_url' => $expectedCallbackUrl,
            'urls_match' => $redirectUriConfig === $expectedCallbackUrl,
            'user_agent' => $request->userAgent(),
            'ip' => $request->ip(),
            'method' => $request->method()
        ]);

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
            // Obtener información del usuario de Google
            // Esto puede lanzar excepciones de Socialite si hay problemas con OAuth
            try {
                $redirectUriConfig = config('services.google.redirect');
                $code = $request->query('code', '');
                $state = $request->query('state', '');
                
                // #region agent log
                $debugLogPath = base_path('.cursor/debug.log');
            @file_put_contents($debugLogPath, json_encode([
                    'sessionId' => 'debug-session',
                    'runId' => 'run1',
                    'hypothesisId' => 'A,B,C,D',
                    'location' => 'GoogleAuthController.php:138',
                    'message' => 'handleGoogleCallback: Antes de obtener usuario',
                    'data' => [
                        'redirect_uri_config' => $redirectUriConfig,
                        'redirect_uri_length' => strlen($redirectUriConfig),
                        'has_code' => $request->has('code'),
                        'code_length' => strlen($code),
                        'has_state' => $request->has('state'),
                        'state_length' => strlen($state),
                        'full_url' => $request->fullUrl()
                    ],
                    'timestamp' => time() * 1000
                ]) . "\n", FILE_APPEND | LOCK_EX);
                // #endregion
                
                Log::info('Intentando obtener usuario de Google', [
                    'has_code' => $request->has('code'),
                    'code_length' => strlen($code),
                    'redirect_uri_config' => $redirectUriConfig,
                    'full_url' => $request->fullUrl(),
                    'query_params' => $request->query()
                ]);
                
                // #region agent log
                $debugLogPath = base_path('.cursor/debug.log');
            @file_put_contents($debugLogPath, json_encode([
                    'sessionId' => 'debug-session',
                    'runId' => 'run1',
                    'hypothesisId' => 'A,D',
                    'location' => 'GoogleAuthController.php:150',
                    'message' => 'handleGoogleCallback: Antes de llamar Socialite->user()',
                    'data' => [
                        'redirect_uri_que_se_usara' => $redirectUriConfig,
                        'stateless_mode' => true
                    ],
                    'timestamp' => time() * 1000
                ]) . "\n", FILE_APPEND | LOCK_EX);
                // #endregion
                
                // #region agent log
                $debugLogPath = base_path('.cursor/debug.log');
                @file_put_contents($debugLogPath, json_encode([
                    'sessionId' => 'debug-session',
                    'runId' => 'run3',
                    'hypothesisId' => 'A,D',
                    'location' => 'GoogleAuthController.php:242',
                    'message' => 'handleGoogleCallback: CON redirectUrl en callback (restaurado)',
                    'data' => [
                        'redirect_uri_config' => $redirectUriConfig,
                        'usando_redirectUrl' => true,
                        'stateless_mode' => true
                    ],
                    'timestamp' => time() * 1000
                ]) . "\n", FILE_APPEND | LOCK_EX);
                // #endregion
                
                // CRÍTICO: En modo stateless, debemos especificar el redirectUrl explícitamente
                // para que coincida exactamente con el usado en redirectToGoogle
                // IMPORTANTE: El redirectUrl debe ser el mismo que se usó en la solicitud inicial
                $googleUser = Socialite::driver('google')
                    ->stateless()
                    ->redirectUrl($redirectUriConfig)
                    ->user();
                
                // #region agent log
                $debugLogPath = base_path('.cursor/debug.log');
            @file_put_contents($debugLogPath, json_encode([
                    'sessionId' => 'debug-session',
                    'runId' => 'run1',
                    'hypothesisId' => 'A,D',
                    'location' => 'GoogleAuthController.php:155',
                    'message' => 'handleGoogleCallback: Después de obtener usuario exitosamente',
                    'data' => [
                        'google_id' => $googleUser->getId(),
                        'email' => $googleUser->getEmail(),
                        'name' => $googleUser->getName()
                    ],
                    'timestamp' => time() * 1000
                ]) . "\n", FILE_APPEND | LOCK_EX);
                // #endregion
                    
                Log::info('Usuario de Google obtenido exitosamente', [
                    'google_id' => $googleUser->getId(),
                    'email' => $googleUser->getEmail()
                ]);
            } catch (\Laravel\Socialite\Two\InvalidStateException $e) {
                // #region agent log
                $debugLogPath = base_path('.cursor/debug.log');
            @file_put_contents($debugLogPath, json_encode([
                    'sessionId' => 'debug-session',
                    'runId' => 'run1',
                    'hypothesisId' => 'C',
                    'location' => 'GoogleAuthController.php:157',
                    'message' => 'handleGoogleCallback: InvalidStateException capturada',
                    'data' => [
                        'error' => $e->getMessage(),
                        'error_class' => get_class($e)
                    ],
                    'timestamp' => time() * 1000
                ]) . "\n", FILE_APPEND | LOCK_EX);
                // #endregion
                Log::error('InvalidStateException en Google OAuth', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw new Exception('Sesión de autenticación inválida. Por favor, intenta de nuevo.');
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                $response = $e->getResponse();
                $responseBody = $response ? $response->getBody()->getContents() : null;
                $statusCode = $response ? $response->getStatusCode() : null;
                
                // #region agent log
                $debugLogPath = base_path('.cursor/debug.log');
                @file_put_contents($debugLogPath, json_encode([
                    'sessionId' => 'debug-session',
                    'runId' => 'run4',
                    'hypothesisId' => 'A,B,D,E',
                    'location' => 'GoogleAuthController.php:328',
                    'message' => 'handleGoogleCallback: ClientException capturada',
                    'data' => [
                        'error' => $e->getMessage(),
                        'error_class' => get_class($e),
                        'response_status' => $statusCode,
                        'response_body' => $responseBody,
                        'redirect_uri_used' => $redirectUriConfig
                    ],
                    'timestamp' => time() * 1000
                ]) . "\n", FILE_APPEND | LOCK_EX);
                // #endregion
                
                Log::error('ClientException en Google OAuth', [
                    'error' => $e->getMessage(),
                    'status_code' => $statusCode,
                    'response' => $responseBody,
                    'redirect_uri_used' => $redirectUriConfig,
                    'trace' => $e->getTraceAsString()
                ]);
                
                // Proporcionar mensaje más específico basado en el código de estado
                if ($statusCode === 400) {
                    throw new Exception('Solicitud inválida a Google. Verifica la configuración de OAuth.');
                } elseif ($statusCode === 401) {
                    throw new Exception('Credenciales de Google OAuth inválidas. Verifica CLIENT_ID y CLIENT_SECRET.');
                } else {
                    throw new Exception('Error al comunicarse con Google (código ' . $statusCode . '). Por favor, intenta de nuevo.');
                }
            } catch (\GuzzleHttp\Exception\ServerException $e) {
                // #region agent log
                $debugLogPath = base_path('.cursor/debug.log');
            @file_put_contents($debugLogPath, json_encode([
                    'sessionId' => 'debug-session',
                    'runId' => 'run1',
                    'hypothesisId' => 'E',
                    'location' => 'GoogleAuthController.php:171',
                    'message' => 'handleGoogleCallback: ServerException capturada',
                    'data' => [
                        'error' => $e->getMessage(),
                        'error_class' => get_class($e),
                        'response_status' => $e->getResponse() ? $e->getResponse()->getStatusCode() : null
                    ],
                    'timestamp' => time() * 1000
                ]) . "\n", FILE_APPEND | LOCK_EX);
                // #endregion
                Log::error('ServerException en Google OAuth', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw new Exception('Error en el servidor de Google. Por favor, intenta más tarde.');
            } catch (\Exception $e) {
                // #region agent log
                $debugLogPath = base_path('.cursor/debug.log');
            @file_put_contents($debugLogPath, json_encode([
                    'sessionId' => 'debug-session',
                    'runId' => 'run1',
                    'hypothesisId' => 'A,B,C,D,E',
                    'location' => 'GoogleAuthController.php:177',
                    'message' => 'handleGoogleCallback: Excepción genérica capturada',
                    'data' => [
                        'error' => $e->getMessage(),
                        'error_class' => get_class($e)
                    ],
                    'timestamp' => time() * 1000
                ]) . "\n", FILE_APPEND | LOCK_EX);
                // #endregion
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

            // Si hay error, redirigir a deep link de error
            if (!$result['success']) {
                Log::error('Error en callback de Google', [
                    'result' => $result,
                    'google_data' => $googleUserData,
                    'original_message' => $result['message'] ?? 'Sin mensaje'
                ]);
                
                // Usar el mensaje original del caso de uso, que ya es descriptivo
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

            Log::info('Redirigiendo a deep link de éxito', [
                'deep_link' => $deepLink,
                'user_id' => $userId,
                'is_new_user' => $isNewUser
            ]);

            // Redirigir usando una página intermedia HTML que intente abrir el deep link
            // Esto funciona tanto en navegadores como en apps móviles
            return $this->redirectToDeepLinkWithFallback($deepLink, $token, $userId, $nombre, $apellidos, $email, $isNewUser);

        } catch (\Throwable $e) {
            // #region agent log
            $debugLogPath = base_path('.cursor/debug.log');
            @file_put_contents($debugLogPath, json_encode([
                'sessionId' => 'debug-session',
                'runId' => 'run4',
                'hypothesisId' => 'ALL',
                'location' => 'GoogleAuthController.php:475',
                'message' => 'handleGoogleCallback: EXCEPCIÓN CAPTURADA',
                'data' => [
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => substr($e->getTraceAsString(), 0, 1000) // Primeros 1000 caracteres del trace
                ],
                'timestamp' => time() * 1000
            ]) . "\n", FILE_APPEND | LOCK_EX);
            // #endregion
            
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
     * Redirige al deep link usando una página intermedia HTML
     * Esto permite que funcione tanto en navegadores como en apps móviles
     */
    private function redirectToDeepLinkWithFallback(
        string $deepLink, 
        string $token, 
        string $userId, 
        string $nombre, 
        string $apellidos, 
        string $email, 
        bool $isNewUser
    ): RedirectResponse {
        // Crear una página HTML que intente abrir el deep link
        $html = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redirigiendo a la app...</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .container {
            text-align: center;
            padding: 2rem;
        }
        .spinner {
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-top: 4px solid white;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .message {
            font-size: 1.2rem;
            margin-bottom: 1rem;
        }
        .fallback-link {
            display: inline-block;
            margin-top: 1rem;
            padding: 0.75rem 1.5rem;
            background: white;
            color: #667eea;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="spinner"></div>
        <div class="message">Redirigiendo a la app...</div>
        <div style="font-size: 0.9rem; opacity: 0.8;">Si no se abre automáticamente, haz clic en el botón de abajo</div>
        <a href="{$deepLink}" class="fallback-link" id="deepLinkBtn">Abrir en la app</a>
    </div>
    <script>
        (function() {
            var deepLink = "{$deepLink}";
            var isMobile = /Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
            var linkClicked = false;
            
            // Función para intentar abrir el deep link
            function tryOpenDeepLink() {
                if (linkClicked) return;
                linkClicked = true;
                
                if (isMobile) {
                    // En móvil, intentar abrir directamente
                    window.location.href = deepLink;
                } else {
                    // En escritorio, usar un iframe oculto primero (método más compatible)
                    var iframe = document.createElement('iframe');
                    iframe.style.display = 'none';
                    iframe.src = deepLink;
                    document.body.appendChild(iframe);
                    
                    // Después de un momento, intentar con window.location como fallback
                    setTimeout(function() {
                        try {
                            window.location.href = deepLink;
                        } catch(e) {
                            // Si falla, mostrar el botón
                            showFallbackButton();
                        }
                    }, 500);
                }
            }
            
            // Función para mostrar el botón de fallback
            function showFallbackButton() {
                document.getElementById('deepLinkBtn').style.display = 'inline-block';
                document.querySelector('.message').textContent = 'Haz clic en el botón para abrir la app';
            }
            
            // Intentar abrir después de un pequeño delay para que la página se renderice
            setTimeout(tryOpenDeepLink, 100);
            
            // Si después de 2 segundos no se ha abierto, mostrar el botón
            setTimeout(function() {
                if (!linkClicked) {
                    showFallbackButton();
                }
            }, 2000);
            
            // Manejar clic en el botón
            document.getElementById('deepLinkBtn').addEventListener('click', function(e) {
                e.preventDefault();
                tryOpenDeepLink();
            });
        })();
    </script>
</body>
</html>
HTML;

        return response($html, 200)->header('Content-Type', 'text/html');
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
        
        Log::info('Redirigiendo a deep link de error', [
            'deep_link' => $deepLink,
            'error_message' => $errorMessage,
            'status_code' => $statusCode
        ]);
        
        // Usar la misma página intermedia para errores
        $html = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error de autenticación</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
        .container {
            text-align: center;
            padding: 2rem;
            max-width: 500px;
        }
        .error-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        .error-message {
            font-size: 1.1rem;
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }
        .fallback-link {
            display: inline-block;
            margin-top: 1rem;
            padding: 0.75rem 1.5rem;
            background: white;
            color: #f5576c;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-icon">⚠️</div>
        <div class="error-message">{$errorMessage}</div>
        <a href="{$deepLink}" class="fallback-link" id="deepLinkBtn">Volver a la app</a>
    </div>
    <script>
        // Intentar abrir el deep link inmediatamente
        window.location.href = "{$deepLink}";
        
        // Si está en un navegador móvil, intentar abrir el deep link
        if (/Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
            window.location.href = "{$deepLink}";
        }
    </script>
</body>
</html>
HTML;

        return response($html, 200)->header('Content-Type', 'text/html');
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

