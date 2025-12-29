<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Domain\Ports\TokenServiceInterface;
use App\Domain\Ports\UserRepositoryInterface;

class WebJwtAuth
{
    private TokenServiceInterface $tokenService;
    private UserRepositoryInterface $userRepository;

    public function __construct(
        TokenServiceInterface $tokenService,
        UserRepositoryInterface $userRepository
    ) {
        $this->tokenService = $tokenService;
        $this->userRepository = $userRepository;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            // Intentar obtener token de varios lugares
            $token = $this->getToken($request);
            
            if (!$token) {
                return redirect()->route('subscription.premium')
                    ->with('error', 'Debes iniciar sesión para acceder a esta página')
                    ->with('redirect_to', $request->fullUrl());
            }

            // Validar token JWT
            try {
                $payload = $this->tokenService->verifyToken($token);
            } catch (\Exception $e) {
                return redirect()->route('subscription.premium')
                    ->with('error', 'Tu sesión ha expirado. Por favor inicia sesión nuevamente')
                    ->with('redirect_to', $request->fullUrl());
            }

            // Verificar que el usuario existe
            $user = $this->userRepository->findById($payload['user_id']);
            
            if (!$user) {
                return redirect()->route('subscription.premium')
                    ->with('error', 'Usuario no encontrado. Por favor inicia sesión');
            }

            // Verificar que el email esté verificado
            if (!$user->isEmailVerificado()) {
                return redirect()->route('subscription.premium')
                    ->with('error', 'Por favor verifica tu email antes de continuar');
            }

            // Agregar usuario autenticado al request y guardar token en sesión
            $request->attributes->set('authenticated_user', $user);
            $request->attributes->set('token_payload', $payload);
            session(['jwt_token' => $token]);

            return $next($request);

        } catch (\Exception $e) {
            return redirect()->route('subscription.premium')
                ->with('error', 'Error de autenticación. Por favor inicia sesión');
        }
    }

    /**
     * Obtener token de diferentes fuentes
     */
    private function getToken(Request $request): ?string
    {
        // 1. Intentar obtener de parámetro de URL (?token=...)
        $token = $request->query('token');
        if ($token) {
            return $token;
        }

        // 2. Intentar obtener de la sesión
        $token = session('jwt_token');
        if ($token) {
            return $token;
        }

        // 3. Intentar obtener del header Authorization
        $authHeader = $request->header('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }

        // 4. Intentar obtener de cookie
        $token = $request->cookie('jwt_token');
        if ($token) {
            return $token;
        }

        return null;
    }
}
