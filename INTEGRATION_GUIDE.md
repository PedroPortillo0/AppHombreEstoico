# Guía de Integración: Botón de Suscripción → Pago

## 📍 URLs Disponibles

1. **Página de presentación**: `/subscription/premium`
   - Muestra los beneficios del plan Premium
   - Botón "Suscribirme ahora"

2. **Página de pago**: `/subscription/payment`
   - Formulario para ingresar datos de tarjeta
   - Se conecta con la API de OpenPay

3. **Estado de suscripción**: `/subscription/status`
   - Ver el estado actual de la suscripción

---

## 🚀 Flujo de Usuario

```
Usuario en App → Presiona "Suscribirme" → /subscription/premium
                                          ↓
                             Presiona "Suscribirme ahora"
                                          ↓
                              /subscription/payment (formulario)
                                          ↓
                           Ingresa datos de tarjeta
                                          ↓
                    API procesa pago → POST /api/subscriptions
                                          ↓
                              /subscription/status (éxito)
```

---

## 🔑 Autenticación: Paso Importante

Para que el formulario de pago funcione, necesitas pasar el JWT token del usuario. Hay dos formas:

### Opción 1: Desde tu App Móvil (Recomendado)

Si llamas desde una app móvil, pasa el token como parámetro:

```dart
// Flutter ejemplo
Navigator.push(
  context,
  MaterialPageRoute(
    builder: (context) => WebView(
      initialUrl: 'https://tu-dominio.com/subscription/payment?token=$jwtToken',
    ),
  ),
);
```

Luego actualiza la vista `payment.blade.php` para leer el token de la URL:

```javascript
// En payment.blade.php, línea ~242
const urlParams = new URLSearchParams(window.location.search);
const JWT_TOKEN = urlParams.get('token') || '{{ session("jwt_token") ?? "" }}';
```

### Opción 2: Mediante Sesión de Laravel

Si el usuario ya está logueado en web, guarda el token en sesión:

```php
// En tu LoginController o donde manejes el login
session(['jwt_token' => $token]);
```

---

## 📱 Integración desde App Móvil

### Flutter/Dart Ejemplo:

```dart
import 'package:flutter/material.dart';
import 'package:webview_flutter/webview_flutter.dart';

class SubscriptionPage extends StatelessWidget {
  final String jwtToken;
  
  const SubscriptionPage({required this.jwtToken});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('Suscripción Premium')),
      body: WebView(
        initialUrl: 'https://tu-dominio.com/subscription/premium?token=$jwtToken',
        javascriptMode: JavascriptMode.unrestricted,
      ),
    );
  }
}
```

### React Native Ejemplo:

```javascript
import { WebView } from 'react-native-webview';

const SubscriptionScreen = ({ jwtToken }) => {
  return (
    <WebView 
      source={{ 
        uri: `https://tu-dominio.com/subscription/premium?token=${jwtToken}` 
      }}
      style={{ flex: 1 }}
    />
  );
};
```

---

## 🔄 Actualizar la Vista de Pago para Leer Token de URL

Actualiza el archivo `resources/views/subscription/payment.blade.php`:

```javascript
// Busca esta línea (aproximadamente línea 242):
const JWT_TOKEN = '{{ session("jwt_token") ?? "" }}';

// Reemplázala con:
const urlParams = new URLSearchParams(window.location.search);
const JWT_TOKEN = urlParams.get('token') || '{{ session("jwt_token") ?? "" }}';

// Agrega validación
if (!JWT_TOKEN) {
    errorMessage.textContent = 'Token de autenticación no encontrado. Por favor inicia sesión.';
    errorAlert.classList.add('show');
    submitBtn.disabled = true;
}
```

---

## 🧪 Probar el Flujo Completo

### 1. Inicia tu servidor:
```bash
cd Practica01Estadia
php artisan serve
```

### 2. Visita en tu navegador:
```
http://localhost:8000/subscription/premium
```

### 3. Haz clic en "Suscribirme ahora"

### 4. Serás redirigido a:
```
http://localhost:8000/subscription/payment
```

### 5. Ingresa los datos de prueba:
```
Número: 4111111111111111
Nombre: Test User
Mes: 12
Año: 25
CVV: 123
```

### 6. Si todo está bien:
- Se procesará el pago
- Verás mensaje de éxito
- Serás redirigido a `/subscription/status`

---

## ⚠️ Consideraciones de Seguridad

### Producción:
1. **HTTPS obligatorio**: Nunca uses HTTP en producción
2. **Validar token**: Verifica que el token sea válido antes de procesar
3. **CORS**: Configura correctamente si llamas desde app móvil
4. **Rate limiting**: Limita intentos de pago

### Configurar CORS (si es necesario):

En `app/Http/Kernel.php`:
```php
protected $middleware = [
    // ...
    \Fruitcake\Cors\HandleCors::class,
];
```

En `config/cors.php`:
```php
'paths' => ['api/*', 'subscription/*'],
'allowed_origins' => ['tu-dominio-app.com'],
```

---

## 📝 Resumen de Archivos Modificados

✅ **Controlador**: `app/Http/Controllers/SubscriptionController.php`
- Método `showPremium()` - Muestra página de presentación
- Método `showPaymentForm()` - Muestra formulario de pago

✅ **Rutas**: `routes/web.php`
- GET `/subscription/premium`
- GET `/subscription/payment` ← **NUEVA**

✅ **Vistas**:
- `resources/views/subscription/premium.blade.php` - Página de presentación
- `resources/views/subscription/payment.blade.php` - Formulario de pago ← **NUEVO**

✅ **API**: `routes/api.php`
- POST `/api/subscriptions` - Procesa el pago

---

## 🎯 Siguientes Pasos

1. **Configura tus credenciales** de OpenPay en `.env`
2. **Prueba localmente** el flujo completo
3. **Actualiza el token** según tu método de autenticación
4. **Personaliza el diseño** si lo necesitas
5. **Despliega a producción** con HTTPS

---

## 💡 Tips Adicionales

### Redirigir desde cualquier parte de tu app:

```html
<a href="https://tu-dominio.com/subscription/premium?token={{ $jwtToken }}" 
   class="btn btn-primary">
   Actualizar a Premium
</a>
```

### Verificar si el usuario ya tiene suscripción:

```javascript
// Antes de mostrar el botón de suscripción
fetch('/api/subscriptions/status', {
  headers: { 'Authorization': `Bearer ${token}` }
})
.then(res => res.json())
.then(data => {
  if (data.has_subscription) {
    // Ocultar botón o mostrar badge "Premium"
  }
});
```

¡Listo! Ahora tu botón "Suscribirme ahora" llevará al usuario al formulario de pago. 🎉
