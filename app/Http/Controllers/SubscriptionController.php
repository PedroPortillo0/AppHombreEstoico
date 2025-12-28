<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Infrastructure\Services\OpenPayService;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Exception;
use Carbon\Carbon;

class SubscriptionController extends Controller
{
    protected $openPayService;

    public function __construct(OpenPayService $openPayService)
    {
        $this->openPayService = $openPayService;
    }

    /**
     * Mostrar la página de presentación premium
     */
    public function showPremium()
    {
        return view('subscription.premium');
    }

    /**
     * Mostrar el formulario de pago
     */
    public function showPaymentForm()
    {
        return view('subscription.payment');
    }

    /**
     * Crear una nueva suscripción
     * 
     * POST /api/subscriptions
     */
    public function subscribe(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'card_number' => 'required|string',
                'holder_name' => 'required|string',
                'expiration_year' => 'required|string|size:2',
                'expiration_month' => 'required|string|size:2',
                'cvv2' => 'required|string|min:3|max:4',
                'plan_id' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            // Obtener usuario autenticado del middleware JWT
            $user = $request->attributes->get('authenticated_user');

            // Verificar si el usuario ya tiene una suscripción activa
            $activeSubscription = Subscription::where('user_id', $user->getId())
                ->active()
                ->first();

            if ($activeSubscription) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya tienes una suscripción activa'
                ], 400);
            }

            // Obtener la IP del cliente
            $customerIp = $request->ip();
            
            // 1. Crear o obtener cliente en OpenPay
            $existingSubscription = Subscription::where('user_id', $user->getId())->first();
            
            if ($existingSubscription && $existingSubscription->openpay_customer_id) {
                $customerId = $existingSubscription->openpay_customer_id;
            } else {
                $customerResult = $this->openPayService->createCustomer(
                    [
                        'name' => $user->getNombre(),
                        'email' => $user->getEmail(),
                        'external_id' => $user->getId(),
                    ],
                    $customerIp
                );

                // Si el cliente ya existe (external_id duplicado), obtenerlo
                if (!$customerResult['success'] && str_contains($customerResult['error'], 'external_id already exists')) {
                    $customerResult = $this->openPayService->getCustomerByExternalId($user->getId(), $customerIp);
                }

                if (!$customerResult['success']) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Error al crear/obtener cliente en OpenPay',
                        'error' => $customerResult['error']
                    ], 500);
                }

                $customerId = $customerResult['customer_id'];
            }

            // 2. Agregar tarjeta al cliente
            // NOTA: En producción, el device_session_id debe venir del frontend
            // generado usando OpenPay.deviceData.setup()
            $cardResult = $this->openPayService->addCard(
                $customerId,
                [
                    'card_number' => $request->card_number,
                    'holder_name' => $request->holder_name,
                    'expiration_year' => $request->expiration_year,
                    'expiration_month' => $request->expiration_month,
                    'cvv2' => $request->cvv2,
                ],
                null, // device_session_id - debe venir del frontend en producción
                $customerIp
            );

            if (!$cardResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al registrar tarjeta',
                    'error' => $cardResult['error']
                ], 500);
            }

            $cardId = $cardResult['card_id'];

            // 3. Crear o usar plan existente (Plan Premium $99.99/mes)
            $planId = $request->plan_id ?? config('services.openpay.default_plan_id');
            
            if (!$planId) {
                // Crear el plan si no existe
                $planResult = $this->openPayService->createPlan([
                    'name' => 'Plan Premium',
                    'amount' => 99.99,
                    'repeat_every' => 1,
                    'repeat_unit' => 'month',
                    'retry_times' => 3,
                    'status_after_retry' => 'cancelled',
                    'trial_days' => 0,
                ], $customerIp);

                if (!$planResult['success']) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Error al crear plan de suscripción',
                        'error' => $planResult['error']
                    ], 500);
                }

                $planId = $planResult['plan_id'];
            }

            // 4. Crear la suscripción en OpenPay
            $subscriptionResult = $this->openPayService->createSubscription($customerId, [
                'plan_id' => $planId,
                'card_id' => $cardId,
            ], $customerIp);

            if (!$subscriptionResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al crear suscripción',
                    'error' => $subscriptionResult['error']
                ], 500);
            }

            $openpaySubscription = $subscriptionResult['subscription'];

            // 5. Guardar la suscripción en la base de datos
            $subscription = Subscription::create([
                'user_id' => $user->getId(),
                'openpay_customer_id' => $customerId,
                'openpay_subscription_id' => $openpaySubscription->id,
                'openpay_plan_id' => $planId,
                'openpay_card_id' => $cardId,
                'plan_name' => 'Premium',
                'amount' => 99.99,
                'currency' => 'MXN',
                'interval' => 'month',
                'status' => $openpaySubscription->status,
                'current_period_start' => Carbon::now(),
                'current_period_end' => Carbon::now()->addMonth(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Suscripción creada exitosamente',
                'subscription' => $subscription
            ], 201);

        } catch (Exception $e) {
            Log::error('Error en suscripción: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la suscripción',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener el estado de la suscripción del usuario autenticado
     * 
     * GET /api/subscriptions/status
     */
    public function status(Request $request)
    {
        try {
            // Obtener usuario autenticado del middleware JWT
            $user = $request->attributes->get('authenticated_user');
            
            $subscription = Subscription::where('user_id', $user->getId())
                ->latest()
                ->first();

            if (!$subscription) {
                return response()->json([
                    'success' => true,
                    'has_subscription' => false,
                    'message' => 'No tienes una suscripción activa'
                ]);
            }

            // Sincronizar con OpenPay si está activa
            if ($subscription->openpay_subscription_id && $subscription->isActive()) {
                $openpayResult = $this->openPayService->getSubscription(
                    $subscription->openpay_customer_id,
                    $subscription->openpay_subscription_id
                );

                if ($openpayResult['success']) {
                    $openpaySubscription = $openpayResult['subscription'];
                    
                    // Actualizar estado local con OpenPay
                    $subscription->update([
                        'status' => $openpaySubscription->status,
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'has_subscription' => true,
                'subscription' => $subscription
            ]);

        } catch (Exception $e) {
            Log::error('Error al obtener estado de suscripción: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estado de suscripción',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancelar la suscripción del usuario
     * 
     * DELETE /api/subscriptions
     */
    public function cancel(Request $request)
    {
        try {
            // Obtener usuario autenticado del middleware JWT
            $user = $request->attributes->get('authenticated_user');
            
            $subscription = Subscription::where('user_id', $user->getId())
                ->active()
                ->first();

            if (!$subscription) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes una suscripción activa para cancelar'
                ], 404);
            }

            // Cancelar en OpenPay
            if ($subscription->openpay_subscription_id) {
                $result = $this->openPayService->cancelSubscription(
                    $subscription->openpay_customer_id,
                    $subscription->openpay_subscription_id
                );

                if (!$result['success']) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Error al cancelar en OpenPay',
                        'error' => $result['error']
                    ], 500);
                }
            }

            // Marcar como cancelada localmente
            $subscription->markAsCancelled();

            return response()->json([
                'success' => true,
                'message' => 'Suscripción cancelada exitosamente',
                'subscription' => $subscription
            ]);

        } catch (Exception $e) {
            Log::error('Error al cancelar suscripción: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al cancelar suscripción',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Listar todas las suscripciones del usuario
     * 
     * GET /api/subscriptions
     */
    public function index(Request $request)
    {
        try {
            // Obtener usuario autenticado del middleware JWT
            $user = $request->attributes->get('authenticated_user');
            
            $subscriptions = Subscription::where('user_id', $user->getId())
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'subscriptions' => $subscriptions
            ]);

        } catch (Exception $e) {
            Log::error('Error al listar suscripciones: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al listar suscripciones',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Webhook de OpenPay para recibir notificaciones
     * 
     * POST /api/subscriptions/webhook
     */
    public function webhook(Request $request)
    {
        try {
            Log::info('Webhook de OpenPay recibido', $request->all());

            $type = $request->input('type');
            $transaction = $request->input('transaction');

            switch ($type) {
                case 'charge.succeeded':
                    $this->handleChargeSucceeded($transaction);
                    break;

                case 'charge.failed':
                    $this->handleChargeFailed($transaction);
                    break;

                case 'subscription.charge.failed':
                    $this->handleSubscriptionChargeFailed($transaction);
                    break;

                case 'subscription.cancelled':
                    $this->handleSubscriptionCancelled($transaction);
                    break;

                default:
                    Log::info('Tipo de webhook no manejado: ' . $type);
            }

            return response()->json(['success' => true], 200);

        } catch (Exception $e) {
            Log::error('Error en webhook: ' . $e->getMessage());
            return response()->json(['success' => false], 500);
        }
    }

    /**
     * Manejar cargo exitoso
     */
    private function handleChargeSucceeded($transaction)
    {
        if (isset($transaction['subscription_id'])) {
            $subscription = Subscription::where('openpay_subscription_id', $transaction['subscription_id'])
                ->first();

            if ($subscription) {
                $subscription->update([
                    'status' => 'active',
                    'current_period_start' => Carbon::now(),
                    'current_period_end' => Carbon::now()->addMonth(),
                ]);
            }
        }
    }

    /**
     * Manejar cargo fallido
     */
    private function handleChargeFailed($transaction)
    {
        if (isset($transaction['subscription_id'])) {
            $subscription = Subscription::where('openpay_subscription_id', $transaction['subscription_id'])
                ->first();

            if ($subscription) {
                $subscription->update([
                    'status' => 'past_due',
                ]);
            }
        }
    }

    /**
     * Manejar fallo de cargo de suscripción
     */
    private function handleSubscriptionChargeFailed($transaction)
    {
        $this->handleChargeFailed($transaction);
    }

    /**
     * Manejar cancelación de suscripción
     */
    private function handleSubscriptionCancelled($transaction)
    {
        if (isset($transaction['id'])) {
            $subscription = Subscription::where('openpay_subscription_id', $transaction['id'])
                ->first();

            if ($subscription) {
                $subscription->markAsCancelled();
            }
        }
    }
}
