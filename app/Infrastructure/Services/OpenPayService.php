<?php

namespace App\Infrastructure\Services;

use Openpay\Data\Openpay;
use Exception;
use Illuminate\Support\Facades\Log;

class OpenPayService
{
    private $merchantId;
    private $privateKey;
    private $publicKey;

    public function __construct()
    {
        $this->merchantId = config('services.openpay.merchant_id');
        $this->privateKey = config('services.openpay.private_key');
        $this->publicKey = config('services.openpay.public_key');
        
        // Configurar sandbox mode una sola vez al inicializar el servicio
        $sandboxMode = config('services.openpay.sandbox_mode', true);
        Openpay::setSandboxMode($sandboxMode);
    }
    
    /**
     * Obtener instancia de OpenPay configurada con la IP del cliente
     */
    private function getOpenpayInstance($customerIp = null)
    {
        // Si no se proporciona IP, usar una IP por defecto (solo para sandbox)
        if (!$customerIp) {
            $customerIp = '187.190.222.171'; // IP de ejemplo para sandbox
        }
        
        // Obtener la instancia con la IP del cliente (sandbox mode ya configurado en constructor)
        return Openpay::getInstance(
            $this->merchantId,
            $this->privateKey,
            'MX',  // País en mayúsculas como espera el SDK
            $customerIp
        );
    }

    /**
     * Crear un cliente en OpenPay
     */
    public function createCustomer($userData, $customerIp = null)
    {
        try {
            $openpay = $this->getOpenpayInstance($customerIp);
            
            $customerData = [
                'name' => $userData['name'] ?? '',
                'email' => $userData['email'] ?? '',
                'requires_account' => false,
                'external_id' => $userData['external_id'] ?? null,
            ];

            if (isset($userData['phone'])) {
                $customerData['phone_number'] = $userData['phone'];
            }

            $customer = $openpay->customers->add($customerData);
            
            Log::info('Cliente OpenPay creado', ['customer_id' => $customer->id]);
            
            return [
                'success' => true,
                'customer_id' => $customer->id,
                'customer' => $customer
            ];
        } catch (Exception $e) {
            Log::error('Error al crear cliente OpenPay: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtener un cliente por external_id
     */
    public function getCustomerByExternalId($externalId, $customerIp = null)
    {
        try {
            $openpay = $this->getOpenpayInstance($customerIp);
            
            // Buscar cliente por external_id
            $customers = $openpay->customers->getList([
                'external_id' => $externalId,
                'limit' => 1
            ]);
            
            if (!empty($customers)) {
                $customer = $customers[0];
                Log::info('Cliente OpenPay encontrado por external_id', ['customer_id' => $customer->id]);
                
                return [
                    'success' => true,
                    'customer_id' => $customer->id,
                    'customer' => $customer
                ];
            }
            
            return [
                'success' => false,
                'error' => 'Cliente no encontrado'
            ];
        } catch (Exception $e) {
            Log::error('Error al buscar cliente OpenPay: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Crear una tarjeta para un cliente
     */
    public function addCard($customerId, $cardData, $deviceSessionId = null, $customerIp = null)
    {
        try {
            $openpay = $this->getOpenpayInstance($customerIp);
            $customer = $openpay->customers->get($customerId);
            
            $cardInfo = [
                'card_number' => $cardData['card_number'],
                'holder_name' => $cardData['holder_name'],
                'expiration_year' => $cardData['expiration_year'],
                'expiration_month' => $cardData['expiration_month'],
                'cvv2' => $cardData['cvv2'],
            ];
            
            // Agregar device_session_id si está presente
            if ($deviceSessionId) {
                $cardInfo['device_session_id'] = $deviceSessionId;
            }
            
            $card = $customer->cards->add($cardInfo);

            Log::info('Tarjeta agregada a cliente OpenPay', [
                'customer_id' => $customerId,
                'card_id' => $card->id
            ]);

            return [
                'success' => true,
                'card_id' => $card->id,
                'card' => $card
            ];
        } catch (Exception $e) {
            Log::error('Error al agregar tarjeta: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Crear un cargo (pago único)
     */
    public function createCharge($customerId, $chargeData, $customerIp = null)
    {
        try {
            $openpay = $this->getOpenpayInstance($customerIp);
            $customer = $openpay->customers->get($customerId);
            
            $charge = $customer->charges->create([
                'method' => 'card',
                'source_id' => $chargeData['card_id'],
                'amount' => $chargeData['amount'],
                'currency' => $chargeData['currency'] ?? 'MXN',
                'description' => $chargeData['description'] ?? 'Cargo desde la app',
                'order_id' => $chargeData['order_id'] ?? null,
            ]);

            Log::info('Cargo creado en OpenPay', [
                'charge_id' => $charge->id,
                'amount' => $charge->amount
            ]);

            return [
                'success' => true,
                'charge_id' => $charge->id,
                'charge' => $charge
            ];
        } catch (Exception $e) {
            Log::error('Error al crear cargo: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Crear un plan de suscripción
     */
    public function createPlan($planData, $customerIp = null)
    {
        try {
            $openpay = $this->getOpenpayInstance($customerIp);
            $plan = $openpay->plans->add([
                'name' => $planData['name'],
                'amount' => $planData['amount'],
                'repeat_every' => $planData['repeat_every'] ?? 1,
                'repeat_unit' => $planData['repeat_unit'] ?? 'month', // day, week, month, year
                'retry_times' => $planData['retry_times'] ?? 3,
                'status_after_retry' => $planData['status_after_retry'] ?? 'cancelled',
                'trial_days' => $planData['trial_days'] ?? 0,
            ]);

            Log::info('Plan creado en OpenPay', ['plan_id' => $plan->id]);

            return [
                'success' => true,
                'plan_id' => $plan->id,
                'plan' => $plan
            ];
        } catch (Exception $e) {
            Log::error('Error al crear plan: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Suscribir un cliente a un plan
     */
    public function createSubscription($customerId, $subscriptionData, $customerIp = null)
    {
        try {
            $openpay = $this->getOpenpayInstance($customerIp);
            $customer = $openpay->customers->get($customerId);
            
            $subscription = $customer->subscriptions->add([
                'plan_id' => $subscriptionData['plan_id'],
                'card_id' => $subscriptionData['card_id'],
                'trial_end_date' => $subscriptionData['trial_end_date'] ?? null,
            ]);

            Log::info('Suscripción creada en OpenPay', [
                'subscription_id' => $subscription->id,
                'customer_id' => $customerId
            ]);

            return [
                'success' => true,
                'subscription_id' => $subscription->id,
                'subscription' => $subscription
            ];
        } catch (Exception $e) {
            Log::error('Error al crear suscripción: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtener información de una suscripción
     */
    public function getSubscription($customerId, $subscriptionId, $customerIp = null)
    {
        try {
            $openpay = $this->getOpenpayInstance($customerIp);
            $customer = $openpay->customers->get($customerId);
            $subscription = $customer->subscriptions->get($subscriptionId);

            return [
                'success' => true,
                'subscription' => $subscription
            ];
        } catch (Exception $e) {
            Log::error('Error al obtener suscripción: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Cancelar una suscripción
     */
    public function cancelSubscription($customerId, $subscriptionId, $customerIp = null)
    {
        try {
            $openpay = $this->getOpenpayInstance($customerIp);
            $customer = $openpay->customers->get($customerId);
            $subscription = $customer->subscriptions->get($subscriptionId);
            $subscription->delete();

            Log::info('Suscripción cancelada en OpenPay', [
                'subscription_id' => $subscriptionId,
                'customer_id' => $customerId
            ]);

            return [
                'success' => true,
                'message' => 'Suscripción cancelada exitosamente'
            ];
        } catch (Exception $e) {
            Log::error('Error al cancelar suscripción: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Listar todas las suscripciones de un cliente
     */
    public function listSubscriptions($customerId, $customerIp = null)
    {
        try {
            $openpay = $this->getOpenpayInstance($customerIp);
            $customer = $openpay->customers->get($customerId);
            $subscriptions = $customer->subscriptions->getList([]);

            return [
                'success' => true,
                'subscriptions' => $subscriptions
            ];
        } catch (Exception $e) {
            Log::error('Error al listar suscripciones: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Listar los cargos de una suscripción
     */
    public function listSubscriptionCharges($customerId, $subscriptionId, $customerIp = null)
    {
        try {
            $openpay = $this->getOpenpayInstance($customerIp);
            $customer = $openpay->customers->get($customerId);
            $subscription = $customer->subscriptions->get($subscriptionId);
            
            // Obtener los cargos del cliente y filtrar por la suscripción
            $charges = $customer->charges->getList([]);
            
            $subscriptionCharges = array_filter($charges, function($charge) use ($subscriptionId) {
                return isset($charge->subscription_id) && $charge->subscription_id === $subscriptionId;
            });

            return [
                'success' => true,
                'charges' => array_values($subscriptionCharges)
            ];
        } catch (Exception $e) {
            Log::error('Error al listar cargos de suscripción: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Crear un token de tarjeta (para uso desde el frontend)
     */
    public function createToken($cardData, $customerIp = null)
    {
        try {
            // Este método normalmente se hace desde el frontend usando la API pública
            // pero lo incluimos por completitud
            $openpay = $this->getOpenpayInstance($customerIp);
            $token = $openpay->tokens->create([
                'card_number' => $cardData['card_number'],
                'holder_name' => $cardData['holder_name'],
                'expiration_year' => $cardData['expiration_year'],
                'expiration_month' => $cardData['expiration_month'],
                'cvv2' => $cardData['cvv2'],
            ]);

            return [
                'success' => true,
                'token_id' => $token->id,
                'token' => $token
            ];
        } catch (Exception $e) {
            Log::error('Error al crear token: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
