<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Models\User;
use Exception;

class MobileAuthController extends Controller
{
    /**
     * Endpoint para crear sesión web desde la app móvil
     * 
     * Este endpoint:
     * 1. Recibe el token JWT de la app móvil
     * 2. Valida el token
     * 3. Crea una sesión web válida en el backend
     * 4. Redirige al usuario a la página solicitada (ya autenticado)
     */
    public function mobileLogin(Request $request)
    {
        try {
            // 1. Obtener el token desde el query string
            $token = $request->query('token');
            $redirect = $request->query('redirect', '/');

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token requerido'
                ], 400);
            }

            // 2. Validar el token JWT
            $jwtSecret = config('services.jwt.secret');
            
            if (!$jwtSecret) {
                return response()->json([
                    'success' => false,
                    'message' => 'JWT_SECRET no configurado'
                ], 500);
            }

            try {
                $decoded = JWT::decode($token, new Key($jwtSecret, 'HS256'));
                $userId = $decoded->id;
            } catch (Exception $e) {
                return redirect('/login?error=invalid_token');
            }

            // 3. Buscar el usuario en la base de datos
            $user = User::find($userId);

            if (!$user) {
                return redirect('/login?error=user_not_found');
            }

            // 4. Crear una sesión web válida usando el guard 'web'
            Auth::guard('web')->login($user);
            
            // Regenerar el ID de sesión para prevenir session fixation
            $request->session()->regenerate();
            
            // Marcar la sesión como autenticada
            $request->session()->put('authenticated', true);
            $request->session()->put('user_id', $userId);
            
            // *** IMPORTANTE: Guardar el token JWT en la sesión ***
            // Esto permite que el middleware WebJwtAuth encuentre el token en las siguientes peticiones
            $request->session()->put('jwt_token', $token);

            // 5. Redirigir a la página solicitada
            return redirect($redirect);

        } catch (Exception $e) {
            \Log::error('Error en mobile-login: ' . $e->getMessage());
            return redirect('/login?error=authentication_failed');
        }
    }

    /**
     * Alternativa con respuesta JSON (si prefieres manejar la redirección desde el frontend)
     */
    public function mobileLoginJson(Request $request): JsonResponse
    {
        try {
            // 1. Obtener el token desde el body
            $token = $request->input('token');

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token requerido'
                ], 400);
            }

            // 2. Validar el token JWT
            $jwtSecret = config('services.jwt.secret');
            
            if (!$jwtSecret) {
                return response()->json([
                    'success' => false,
                    'message' => 'JWT_SECRET no configurado'
                ], 500);
            }

            try {
                $decoded = JWT::decode($token, new Key($jwtSecret, 'HS256'));
                $userId = $decoded->id;
            } catch (Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token inválido',
                    'error' => $e->getMessage()
                ], 401);
            }

            // 3. Buscar el usuario en la base de datos
            $user = User::find($userId);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado'
                ], 404);
            }

            // 4. Crear una sesión web válida
            Auth::guard('web')->login($user);
            
            // Regenerar el ID de sesión
            $request->session()->regenerate();
            
            // Marcar la sesión como autenticada
            $request->session()->put('authenticated', true);
            $request->session()->put('user_id', $userId);
            
            // *** IMPORTANTE: Guardar el token JWT en la sesión ***
            $request->session()->put('jwt_token', $token);

            // 5. Retornar respuesta exitosa
            return response()->json([
                'success' => true,
                'message' => 'Sesión web creada exitosamente',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'nombre' => $user->nombre,
                        'email' => $user->email
                    ],
                    'session_id' => session()->getId()
                ]
            ], 200);

        } catch (Exception $e) {
            \Log::error('Error en mobile-login-json: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al crear sesión web',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
