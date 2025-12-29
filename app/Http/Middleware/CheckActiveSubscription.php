<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckActiveSubscription
{
    /**
     * Verificar si el usuario tiene una suscripción activa
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->attributes->get('authenticated_user');

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado'
            ], 401);
        }

        if (!$user->hasActiveSubscription()) {
            return response()->json([
                'success' => false,
                'message' => 'Se requiere una suscripción activa para acceder a esta funcionalidad',
                'subscription_required' => true,
                'has_active_subscription' => false
            ], 403);
        }

        return $next($request);
    }
}
