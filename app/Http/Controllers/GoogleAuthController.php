<?php

namespace App\Http\Controllers;

use App\Application\UseCases\LoginWithGoogle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
     */
    public function handleGoogleCallback(Request $request): JsonResponse
    {
        try {
            // Obtener información del usuario de Google
            $googleUser = Socialite::driver('google')
                ->stateless()
                ->user();

            // Preparar datos del usuario
            $googleUserData = [
                'id' => $googleUser->getId(),
                'email' => $googleUser->getEmail(),
                'name' => $googleUser->getName(),
                'avatar' => $googleUser->getAvatar()
            ];

            // Ejecutar caso de uso de login/registro
            $result = $this->loginWithGoogle->execute($googleUserData);

            return response()->json($result, $result['success'] ? 200 : 400);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error en callback de Google: ' . $e->getMessage()
            ], 500);
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

            // Obtener información del usuario usando el token
            $googleUser = Socialite::driver('google')
                ->stateless()
                ->userFromToken($request->input('access_token'));

            // Preparar datos del usuario
            $googleUserData = [
                'id' => $googleUser->getId(),
                'email' => $googleUser->getEmail(),
                'name' => $googleUser->getName(),
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

