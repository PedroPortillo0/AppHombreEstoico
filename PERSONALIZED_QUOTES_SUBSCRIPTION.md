# 🔒 Sistema de Frases Personalizadas con Suscripción

## 📋 Resumen

Las **frases personalizadas** ahora requieren:
1. ✅ **Quiz completado** (`quiz_completed = true`)
2. ✅ **Suscripción activa** (`status = 'active'` y `ends_at > now()`)

Si el usuario **NO cumple** ambos requisitos → Recibe **frase normal**
Si el usuario **SÍ cumple** ambos requisitos → Recibe **frase personalizada con IA**

---

## 🚀 Cómo Funciona

### **Flujo Automático:**

```
1. Usuario hace login → Obtiene token JWT

2. Usuario llama: GET /api/daily-quote
   ↓
3. Sistema verifica automáticamente:
   - ¿Tiene quiz completado?
   - ¿Tiene suscripción activa?
   ↓
4a. SI cumple ambos requisitos:
    → Genera frase personalizada con IA
    → Guarda en cache (no regenera el mismo día)
    
4b. NO cumple requisitos:
    → Devuelve frase normal del día
```

---

## 📡 Endpoints para Postman

### 1️⃣ **Verificar Acceso a Frases Personalizadas**

**Ruta:** `GET` `http://localhost:8000/api/subscriptions/check-personalized-access`

**Headers:**
```
Authorization: Bearer TU_TOKEN_JWT
Content-Type: application/json
```

**Respuesta:**
```json
{
  "success": true,
  "can_access_personalized_quotes": true,
  "has_active_subscription": true,
  "has_quiz_completed": true,
  "subscription": {
    "id": 1,
    "user_id": "user-123",
    "status": "active",
    "plan_name": "Premium",
    "amount": 99.99,
    "current_period_end": "2025-02-01T00:00:00Z"
  },
  "requirements": {
    "quiz_completed": true,
    "active_subscription": true
  }
}
```

---

### 2️⃣ **Obtener Frase del Día (Automático)**

**Ruta:** `GET` `http://localhost:8000/api/daily-quote`

**Headers:**
```
Authorization: Bearer TU_TOKEN_JWT
Content-Type: application/json
```

**Respuesta con Suscripción Activa:**
```json
{
  "success": true,
  "data": {
    "id": "quote-123",
    "quote": "Tu frase personalizada basada en tus respuestas del quiz...",
    "author": "Marco Aurelio",
    "category": "Virtud",
    "explanation": "Esta frase está personalizada para ti porque...",
    "date": "2025-12-28",
    "day_of_year": 363,
    "is_personalized": true
  }
}
```

**Respuesta SIN Suscripción Activa:**
```json
{
  "success": true,
  "data": {
    "id": "quote-123",
    "quote": "La felicidad de tu vida depende de la calidad de tus pensamientos.",
    "author": "Marco Aurelio",
    "category": "Virtud",
    "date": "2025-12-28",
    "is_personalized": false
  }
}
```

---

### 3️⃣ **Obtener Estado de Suscripción**

**Ruta:** `GET` `http://localhost:8000/api/subscriptions/status`

**Headers:**
```
Authorization: Bearer TU_TOKEN_JWT
Content-Type: application/json
```

**Respuesta:**
```json
{
  "success": true,
  "has_subscription": true,
  "subscription": {
    "id": 1,
    "user_id": "user-123",
    "status": "active",
    "plan_name": "Premium",
    "amount": 99.99,
    "current_period_start": "2025-01-01T00:00:00Z",
    "current_period_end": "2025-02-01T00:00:00Z",
    "is_active": true
  }
}
```

---

## ⚙️ Estados de Suscripción

| Estado | Descripción | Acceso a Frases Personalizadas |
|--------|-------------|-------------------------------|
| `active` | Suscripción activa y pagada | ✅ SÍ (si tiene quiz) |
| `trial` | Período de prueba activo | ✅ SÍ (si tiene quiz) |
| `past_due` | Pago fallido, aún activa | ❌ NO |
| `cancelled` | Cancelada por el usuario | ❌ NO |
| `expired` | Venció el período | ❌ NO |

---

## 🔄 Webhooks Automáticos

OpenPay envía webhooks cuando:

### ✅ **Suscripción Activada** → Desbloquea frases personalizadas
```
POST /api/subscriptions/webhook
{
  "type": "charge.succeeded",
  "transaction": {
    "subscription_id": "sj2flgi4bnoq5itgpy8n"
  }
}
```

### ❌ **Suscripción Vencida** → Bloquea frases personalizadas
```
POST /api/subscriptions/webhook
{
  "type": "subscription.cancelled",
  "transaction": {
    "id": "sj2flgi4bnoq5itgpy8n"
  }
}
```

---

## 🧪 Cómo Probarlo

### **Escenario 1: Usuario SIN suscripción**

```bash
# 1. Login
POST /api/users/login
{
  "email": "usuario@example.com",
  "password": "123456"
}
# → Recibe token

# 2. Completar quiz
POST /api/quiz/submit
{
  "respuestas": {...}
}

# 3. Obtener frase del día
GET /api/daily-quote
Authorization: Bearer TOKEN

# ❌ Resultado: Frase NORMAL (no personalizada)
```

---

### **Escenario 2: Usuario CON suscripción**

```bash
# 1. Login
POST /api/users/login

# 2. Crear suscripción
POST /api/subscriptions
{
  "card_number": "4111111111111111",
  "holder_name": "Juan Perez",
  "expiration_year": "25",
  "expiration_month": "12",
  "cvv2": "123"
}

# 3. Completar quiz (si no lo tiene)
POST /api/quiz/submit

# 4. Obtener frase del día
GET /api/daily-quote
Authorization: Bearer TOKEN

# ✅ Resultado: Frase PERSONALIZADA con IA
```

---

### **Escenario 3: Suscripción Vence**

```bash
# Cuando OpenPay cancela la suscripción automáticamente:
# → Sistema actualiza status = 'cancelled'
# → Próxima llamada a /api/daily-quote devuelve frase NORMAL
```

---

## 📊 Métodos Agregados al Modelo User

```php
// Verificar si tiene suscripción activa
$user->hasActiveSubscription(); // true/false

// Verificar si puede acceder a frases personalizadas
$user->canAccessPersonalizedQuotes(); // true/false

// Obtener suscripción activa
$user->activeSubscription(); // Subscription|null
```

---

## 🎯 Código de Ejemplo

```php
use App\Models\User;

$user = User::find('user-123');

// Verificar acceso
if ($user->canAccessPersonalizedQuotes()) {
    echo "✅ Usuario puede ver frases personalizadas";
} else {
    echo "❌ Usuario ve frases normales";
}

// Ver estado de suscripción
if ($user->hasActiveSubscription()) {
    $subscription = $user->activeSubscription();
    echo "Plan: " . $subscription->plan_name;
    echo "Vence: " . $subscription->current_period_end;
} else {
    echo "No tiene suscripción activa";
}
```

---

## 🚨 Manejo de Errores

### **Usuario sin suscripción intenta acceder**
```json
{
  "success": true,
  "data": {
    "quote": "Frase normal sin personalización",
    "is_personalized": false
  }
}
```

### **Suscripción vencida**
```json
{
  "success": true,
  "has_subscription": true,
  "subscription": {
    "status": "cancelled",
    "ends_at": "2025-01-15T00:00:00Z"
  }
}
```

---

## ✅ Checklist de Implementación

- [x] Modelo `User` con métodos de verificación
- [x] Modelo `Subscription` con método `isActive()`
- [x] Middleware `CheckActiveSubscription`
- [x] Lógica en `GetDailyQuote` para verificar suscripción
- [x] Endpoint `/check-personalized-access`
- [x] Webhooks actualizan estado automáticamente
- [x] Documentación completa

---

## 🎓 Resumen Final

**Regla de Oro:**
```
Frases Personalizadas = Quiz Completado + Suscripción Activa
```

Si **cualquiera de los dos** falta → Usuario recibe **frase normal**

¡El sistema se encarga automáticamente! 🚀
