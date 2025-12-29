<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Subscription;

class CheckSubscription
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            // Obtener el usuario autenticado del middleware anterior (WebJwtAuth)
            $user = $request->attributes->get('authenticated_user');
            
            if (!$user) {
                return redirect()->route('subscription.premium')
                    ->with('error', 'Usuario no autenticado');
            }

            // Verificar si ya tiene una suscripción activa
            $activeSubscription = Subscription::where('user_id', $user->getId())
                ->where('status', 'active')
                ->where(function($query) {
                    $query->whereNull('ends_at')
                          ->orWhere('ends_at', '>', now());
                })
                ->first();

            if ($activeSubscription) {
                return redirect()->route('subscription.status')
                    ->with('info', 'Ya tienes una suscripción activa. Puedes gestionar tu suscripción desde aquí.');
            }

            return $next($request);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error en CheckSubscription middleware: ' . $e->getMessage());
            
            return redirect()->route('subscription.premium')
                ->with('error', 'Ocurrió un error al verificar tu suscripción');
        }
    }
}
