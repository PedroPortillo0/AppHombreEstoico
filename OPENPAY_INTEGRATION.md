# Documentación de Integración con OpenPay

## Configuración Inicial

### 1. Variables de Entorno

Agrega las siguientes variables en tu archivo `.env`:

```env
# OpenPay Configuration
OPENPAY_MERCHANT_ID=tu_merchant_id
OPENPAY_PRIVATE_KEY=tu_private_key
OPENPAY_PUBLIC_KEY=tu_public_key
OPENPAY_SANDBOX_MODE=true
OPENPAY_PRODUCTION_MODE=FALSE
```

### 2. Obtener Credenciales de OpenPay

1. Regístrate en [OpenPay](https://www.openpay.mx/)
2. Obtén tus credenciales en el dashboard
3. En modo sandbox (desarrollo), usa las credenciales de prueba
4. En producción, usa las credenciales reales y cambia `OPENPAY_SANDBOX_MODE=false`

---

## Endpoints Disponibles

### 1. Crear Suscripción

**Endpoint:** `POST /api/subscriptions`

**Headers:**
```
Authorization: Bearer {JWT_TOKEN}
Content-Type: application/json
```

**Body:**
```json
{
  "card_number": "4111111111111111",
  "holder_name": "Juan Pérez",
  "expiration_year": "25",
  "expiration_month": "12",
  "cvv2": "123",
  "plan_id": "optional_plan_id"
}
```

**Respuesta Exitosa (201):**
```json
{
  "success": true,
  "message": "Suscripción creada exitosamente",
  "subscription": {
    "id": 1,
    "user_id": "uuid-del-usuario",
    "openpay_customer_id": "a9pvykxz4g5rg0fplze0",
    "openpay_subscription_id": "s0gmyor4yisbl2kgp6iq",
    "openpay_plan_id": "pbi4kb8hpb64x0uud2eb",
    "openpay_card_id": "kqgykn96i7bcs1wwhvgw",
    "plan_name": "Premium",
    "amount": "99.99",
    "currency": "MXN",
    "interval": "month",
    "status": "active",
    "current_period_start": "2025-12-23T12:00:00.000000Z",
    "current_period_end": "2026-01-23T12:00:00.000000Z",
    "created_at": "2025-12-23T12:00:00.000000Z",
    "updated_at": "2025-12-23T12:00:00.000000Z",
    "is_active": true,
    "on_trial": false,
    "is_cancelled": false,
    "days_until_renewal": 30,
    "days_of_trial_remaining": 0
  }
}
```

**Errores:**
- `400`: Ya tienes una suscripción activa
- `422`: Datos de tarjeta inválidos
- `500`: Error en OpenPay

---

### 2. Obtener Estado de Suscripción

**Endpoint:** `GET /api/subscriptions/status`

**Headers:**
```
Authorization: Bearer {JWT_TOKEN}
```

**Respuesta Exitosa (200):**
```json
{
  "success": true,
  "has_subscription": true,
  "subscription": {
    "id": 1,
    "user_id": "uuid-del-usuario",
    "status": "active",
    "plan_name": "Premium",
    "amount": "99.99",
    "currency": "MXN",
    "current_period_end": "2026-01-23T12:00:00.000000Z",
    "is_active": true,
    "days_until_renewal": 30
  }
}
```

**Sin Suscripción (200):**
```json
{
  "success": true,
  "has_subscription": false,
  "message": "No tienes una suscripción activa"
}
```

---

### 3. Listar Todas las Suscripciones

**Endpoint:** `GET /api/subscriptions`

**Headers:**
```
Authorization: Bearer {JWT_TOKEN}
```

**Respuesta Exitosa (200):**
```json
{
  "success": true,
  "subscriptions": [
    {
      "id": 1,
      "status": "active",
      "plan_name": "Premium",
      "amount": "99.99",
      "created_at": "2025-12-23T12:00:00.000000Z"
    },
    {
      "id": 2,
      "status": "cancelled",
      "plan_name": "Premium",
      "amount": "99.99",
      "created_at": "2025-11-23T12:00:00.000000Z"
    }
  ]
}
```

---

### 4. Cancelar Suscripción

**Endpoint:** `DELETE /api/subscriptions`

**Headers:**
```
Authorization: Bearer {JWT_TOKEN}
```

**Respuesta Exitosa (200):**
```json
{
  "success": true,
  "message": "Suscripción cancelada exitosamente",
  "subscription": {
    "id": 1,
    "status": "cancelled",
    "cancelled_at": "2025-12-23T12:00:00.000000Z",
    "ends_at": "2025-12-23T12:00:00.000000Z"
  }
}
```

**Errores:**
- `404`: No tienes una suscripción activa para cancelar

---

### 5. Webhook de OpenPay

**Endpoint:** `POST /api/subscriptions/webhook`

Este endpoint recibe notificaciones de OpenPay automáticamente. **No requiere autenticación**.

**Eventos manejados:**
- `charge.succeeded`: Cargo exitoso
- `charge.failed`: Cargo fallido
- `subscription.charge.failed`: Fallo en cargo de suscripción
- `subscription.cancelled`: Suscripción cancelada

**Configuración en OpenPay:**

1. Ve a tu dashboard de OpenPay
2. Navega a Configuración → Webhooks
3. Agrega la URL: `https://tu-dominio.com/api/subscriptions/webhook`
4. Selecciona los eventos que deseas recibir

---

## Integración desde tu App

### Ejemplo de Flujo Completo

#### 1. Desde tu vista (botón "Suscribirme ahora")

```javascript
// Cuando el usuario presiona el botón
async function handleSubscribe() {
  try {
    // Recopilar datos de la tarjeta
    const cardData = {
      card_number: document.getElementById('card_number').value,
      holder_name: document.getElementById('holder_name').value,
      expiration_year: document.getElementById('exp_year').value,
      expiration_month: document.getElementById('exp_month').value,
      cvv2: document.getElementById('cvv').value
    };

    // Llamar a tu API
    const response = await fetch('https://tu-api.com/api/subscriptions', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('token')}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(cardData)
    });

    const data = await response.json();

    if (data.success) {
      // Redirigir a página de éxito
      window.location.href = '/dashboard?subscribed=true';
    } else {
      // Mostrar error
      alert(data.message);
    }
  } catch (error) {
    console.error('Error:', error);
    alert('Error al procesar la suscripción');
  }
}
```

#### 2. Verificar si el usuario tiene suscripción activa

```javascript
async function checkSubscriptionStatus() {
  try {
    const response = await fetch('https://tu-api.com/api/subscriptions/status', {
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('token')}`
      }
    });

    const data = await response.json();

    if (data.has_subscription && data.subscription.is_active) {
      // Usuario tiene suscripción activa
      showPremiumContent();
    } else {
      // Mostrar opción de suscribirse
      showSubscribeButton();
    }
  } catch (error) {
    console.error('Error:', error);
  }
}
```

---

## Tarjetas de Prueba (Sandbox)

Para probar en modo sandbox, usa estas tarjetas:

### Tarjeta Exitosa
```
Número: 4111111111111111
Titular: Juan Pérez
Vencimiento: 12/25
CVV: 123
```

### Tarjeta Rechazada (Fondos Insuficientes)
```
Número: 4000000000000002
Titular: Juan Pérez
Vencimiento: 12/25
CVV: 123
```

### Tarjeta Rechazada (Tarjeta Robada)
```
Número: 4000000000000119
Titular: Juan Pérez
Vencimiento: 12/25
CVV: 123
```

---

## Estados de Suscripción

- **active**: Suscripción activa y al día
- **cancelled**: Suscripción cancelada por el usuario
- **past_due**: Pago vencido (se reintentará el cargo)
- **trial**: En período de prueba
- **unpaid**: Sin pagar después de reintentos

---

## Seguridad

### Importante:
1. **Nunca** almacenes números de tarjeta completos en tu base de datos
2. OpenPay maneja toda la información sensible
3. Solo guardamos el `card_id` que proporciona OpenPay
4. El webhook **debe** validar que viene de OpenPay (implementar validación de firma)

### Validación de Webhook (Recomendado)

```php
// Agregar en el método webhook()
$signature = $request->header('X-Openpay-Signature');
$body = $request->getContent();
$calculatedSignature = hash_hmac('sha256', $body, config('services.openpay.private_key'));

if ($signature !== $calculatedSignature) {
    return response()->json(['error' => 'Invalid signature'], 403);
}
```

---

## Testing

### Probar desde Postman

1. **Crear Suscripción**
   - Method: POST
   - URL: `http://localhost:8000/api/subscriptions`
   - Headers: 
     - Authorization: Bearer {tu_jwt_token}
     - Content-Type: application/json
   - Body:
     ```json
     {
       "card_number": "4111111111111111",
       "holder_name": "Test User",
       "expiration_year": "25",
       "expiration_month": "12",
       "cvv2": "123"
     }
     ```

2. **Ver Estado**
   - Method: GET
   - URL: `http://localhost:8000/api/subscriptions/status`
   - Headers: Authorization: Bearer {tu_jwt_token}

3. **Cancelar**
   - Method: DELETE
   - URL: `http://localhost:8000/api/subscriptions`
   - Headers: Authorization: Bearer {tu_jwt_token}

---

## Soporte

Para más información sobre OpenPay:
- Documentación: https://www.openpay.mx/docs/
- Sandbox Dashboard: https://sandbox-dashboard.openpay.mx/
- Production Dashboard: https://dashboard.openpay.mx/

## Notas Importantes

1. El plan "Premium" se crea automáticamente la primera vez o puedes crear uno manualmente en el dashboard de OpenPay
2. Los cargos son recurrentes automáticamente cada mes
3. Los webhooks son esenciales para mantener sincronizado el estado
4. En producción, asegúrate de cambiar las credenciales y `OPENPAY_SANDBOX_MODE=false`
