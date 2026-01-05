# Guía de Implementación: Google OAuth en Flutter

Esta guía explica cómo implementar el login con Google en tu aplicación Flutter usando el endpoint `/api/auth/google/token` del backend.

---

## 📋 Tabla de Contenidos

1. [Dependencias](#1-dependencias)
2. [Configuración Android](#2-configuración-android)
3. [Configuración iOS](#3-configuración-ios)
4. [Servicio de Autenticación](#4-servicio-de-autenticación)
5. [Ejemplo de Uso](#5-ejemplo-de-uso)
6. [Uso del Token JWT](#6-uso-del-token-jwt)
7. [Flujo Completo](#7-flujo-completo)

---

## 1. Dependencias

### Agregar al `pubspec.yaml`

```yaml
dependencies:
  flutter:
    sdk: flutter
  google_sign_in: ^6.2.1
  http: ^1.2.0
  shared_preferences: ^2.2.2
```

### Instalar dependencias

```bash
flutter pub get
```

---

## 2. Configuración Android

### 2.1. Actualizar `android/app/build.gradle`

Asegúrate de tener el `minSdkVersion` correcto:

```gradle
android {
    defaultConfig {
        minSdkVersion 21  // Mínimo requerido para Google Sign-In
    }
}
```

### 2.2. Verificar `android/app/src/main/AndroidManifest.xml`

Agrega el permiso de internet (si no lo tienes):

```xml
<uses-permission android:name="android.permission.INTERNET"/>
```

### 2.3. Obtener SHA-1 para Google Cloud Console

**En Windows (PowerShell):**
```powershell
cd android
.\gradlew signingReport
```

**En Mac/Linux:**
```bash
cd android
./gradlew signingReport
```

**Pasos:**
1. Copia el SHA-1 que aparece en la consola (búscalo en la sección "Variant: debug")
2. Ve a [Google Cloud Console](https://console.cloud.google.com/)
3. Navega a: **APIs & Services** → **Credentials**
4. Abre tu **OAuth 2.0 Client ID** (tipo Android)
5. En **SHA-1 certificate fingerprints**, agrega el SHA-1 copiado
6. Guarda los cambios

---

## 3. Configuración iOS

### 3.1. Descargar `GoogleService-Info.plist`

1. Ve a [Google Cloud Console](https://console.cloud.google.com/)
2. Navega a **APIs & Services** → **Credentials**
3. Descarga el archivo `GoogleService-Info.plist` para iOS
4. Colócalo en: `ios/Runner/GoogleService-Info.plist`

### 3.2. Actualizar `ios/Runner/Info.plist`

Agrega la URL scheme. Busca el valor `REVERSED_CLIENT_ID` en tu `GoogleService-Info.plist` y úsalo aquí:

```xml
<key>CFBundleURLTypes</key>
<array>
    <dict>
        <key>CFBundleTypeRole</key>
        <string>Editor</string>
        <key>CFBundleURLSchemes</key>
        <array>
            <string>YOUR_REVERSED_CLIENT_ID</string>
        </array>
    </dict>
</array>
```

**Ejemplo:** Si tu `REVERSED_CLIENT_ID` es `com.googleusercontent.apps.123456789`, agrégalo así:

```xml
<string>com.googleusercontent.apps.123456789</string>
```

---

## 4. Servicio de Autenticación

Crea el archivo `lib/services/google_auth_service.dart`:

```dart
import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:google_sign_in/google_sign_in.dart';
import 'package:shared_preferences/shared_preferences.dart';

class GoogleAuthService {
  // Configuración de Google Sign-In
  final GoogleSignIn _googleSignIn = GoogleSignIn(
    scopes: ['email', 'profile'],
  );

  // URL de tu API
  // Para pruebas locales: 'http://localhost:8000/api'
  // Para producción: 'https://web.estoico.app/api'
  static const String apiBaseUrl = 'https://web.estoico.app/api';

  /// Inicia sesión con Google
  /// Retorna un Map con 'success', 'token', 'user' y 'message'
  Future<Map<String, dynamic>?> signInWithGoogle() async {
    try {
      // Paso 1: Iniciar sesión con Google (muestra selector de cuentas)
      final GoogleSignInAccount? googleUser = await _googleSignIn.signIn();
      
      if (googleUser == null) {
        // Usuario canceló el proceso
        return {
          'success': false,
          'message': 'El usuario canceló el inicio de sesión'
        };
      }

      // Paso 2: Obtener el access_token de Google
      final GoogleSignInAuthentication googleAuth = 
          await googleUser.authentication;

      if (googleAuth.accessToken == null) {
        return {
          'success': false,
          'message': 'No se pudo obtener el token de acceso de Google'
        };
      }

      // Paso 3: Enviar el token a tu backend
      final response = await http.post(
        Uri.parse('$apiBaseUrl/auth/google/token'),
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: jsonEncode({
          'access_token': googleAuth.accessToken,
        }),
      );

      // Paso 4: Procesar la respuesta
      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        
        if (data['success'] == true) {
          // Paso 5: Guardar el token JWT
          final jwtToken = data['data']['token'];
          await _saveToken(jwtToken);
          
          // Retornar datos del usuario y token
          return {
            'success': true,
            'token': jwtToken,
            'user': data['data']['user'],
            'message': data['message'] ?? 'Login exitoso'
          };
        } else {
          return {
            'success': false,
            'message': data['message'] ?? 'Error en el servidor'
          };
        }
      } else {
        // Error HTTP
        final errorData = jsonDecode(response.body);
        return {
          'success': false,
          'message': errorData['message'] ?? 'Error al conectar con el servidor',
          'statusCode': response.statusCode
        };
      }
    } catch (e) {
      // Error de red o excepción
      return {
        'success': false,
        'message': 'Error: ${e.toString()}'
      };
    }
  }

  /// Cierra sesión de Google
  Future<void> signOut() async {
    await _googleSignIn.signOut();
    await _removeToken();
  }

  /// Verifica si el usuario ya está autenticado
  Future<bool> isSignedIn() async {
    return await _googleSignIn.isSignedIn();
  }

  /// Obtiene el usuario actual de Google
  Future<GoogleSignInAccount?> getCurrentUser() async {
    return await _googleSignIn.signInSilently();
  }

  // ========== Métodos privados para manejo de tokens ==========

  /// Guarda el token JWT en SharedPreferences
  Future<void> _saveToken(String token) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('jwt_token', token);
  }

  /// Obtiene el token JWT guardado
  Future<String?> getToken() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString('jwt_token');
  }

  /// Elimina el token JWT
  Future<void> _removeToken() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove('jwt_token');
  }
}
```

---

## 5. Ejemplo de Uso

### 5.1. Pantalla de Login

Crea `lib/screens/login_screen.dart`:

```dart
import 'package:flutter/material.dart';
import '../services/google_auth_service.dart';

class LoginScreen extends StatefulWidget {
  @override
  _LoginScreenState createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final GoogleAuthService _authService = GoogleAuthService();
  bool _isLoading = false;

  Future<void> _handleGoogleSignIn() async {
    setState(() {
      _isLoading = true;
    });

    try {
      final result = await _authService.signInWithGoogle();

      if (result != null && result['success'] == true) {
        // Login exitoso
        final token = result['token'];
        final user = result['user'];
        
        // Mostrar mensaje de éxito
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('¡Bienvenido ${user['nombre']}!'),
              backgroundColor: Colors.green,
            ),
          );

          // Navegar a la pantalla principal
          Navigator.pushReplacementNamed(context, '/home');
        }
      } else {
        // Error en el login
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(result?['message'] ?? 'Error al iniciar sesión'),
              backgroundColor: Colors.red,
            ),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Error: ${e.toString()}'),
            backgroundColor: Colors.red,
          ),
        );
      }
    } finally {
      if (mounted) {
        setState(() {
          _isLoading = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('Iniciar Sesión'),
      ),
      body: Center(
        child: Padding(
          padding: EdgeInsets.all(20.0),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Text(
                'Bienvenido',
                style: TextStyle(
                  fontSize: 24,
                  fontWeight: FontWeight.bold,
                ),
              ),
              SizedBox(height: 40),
              
              // Botón de Google Sign-In
              ElevatedButton.icon(
                onPressed: _isLoading ? null : _handleGoogleSignIn,
                icon: _isLoading
                    ? SizedBox(
                        width: 20,
                        height: 20,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : Icon(Icons.login), // O usa una imagen del logo de Google
                label: Text(
                  _isLoading ? 'Iniciando sesión...' : 'Continuar con Google'
                ),
                style: ElevatedButton.styleFrom(
                  padding: EdgeInsets.symmetric(horizontal: 24, vertical: 12),
                  minimumSize: Size(double.infinity, 50),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
```

---

## 6. Uso del Token JWT

### 6.1. Servicio para Peticiones Autenticadas

Crea `lib/services/api_service.dart`:

```dart
import 'package:http/http.dart' as http;
import 'dart:convert';
import 'google_auth_service.dart';

class ApiService {
  final GoogleAuthService _authService = GoogleAuthService();
  static const String apiBaseUrl = 'https://web.estoico.app/api';

  /// Hace una petición autenticada a la API
  Future<Map<String, dynamic>> authenticatedRequest(
    String endpoint, {
    String method = 'GET',
    Map<String, dynamic>? body,
  }) async {
    // Obtener el token JWT
    final token = await _authService.getToken();
    
    if (token == null) {
      throw Exception('Usuario no autenticado');
    }

    // Configurar headers
    final headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'Authorization': 'Bearer $token',
    };

    // Hacer la petición
    http.Response response;
    final uri = Uri.parse('$apiBaseUrl$endpoint');

    switch (method.toUpperCase()) {
      case 'GET':
        response = await http.get(uri, headers: headers);
        break;
      case 'POST':
        response = await http.post(
          uri,
          headers: headers,
          body: body != null ? jsonEncode(body) : null,
        );
        break;
      case 'PUT':
        response = await http.put(
          uri,
          headers: headers,
          body: body != null ? jsonEncode(body) : null,
        );
        break;
      case 'DELETE':
        response = await http.delete(uri, headers: headers);
        break;
      default:
        throw Exception('Método HTTP no soportado');
    }

    // Procesar respuesta
    if (response.statusCode >= 200 && response.statusCode < 300) {
      return jsonDecode(response.body);
    } else {
      throw Exception('Error ${response.statusCode}: ${response.body}');
    }
  }

  /// Ejemplo: Obtener información del usuario
  Future<Map<String, dynamic>> getUserInfo() async {
    return await authenticatedRequest('/users/me');
  }

  /// Ejemplo: Actualizar información del quiz
  Future<Map<String, dynamic>> updateQuizInfo(Map<String, dynamic> quizData) async {
    return await authenticatedRequest(
      '/users/quiz-info',
      method: 'PATCH',
      body: quizData,
    );
  }
}
```

### 6.2. Ejemplo de Uso del ApiService

```dart
import 'package:flutter/material.dart';
import '../services/api_service.dart';

class ProfileScreen extends StatefulWidget {
  @override
  _ProfileScreenState createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  final ApiService _apiService = ApiService();
  Map<String, dynamic>? _userInfo;
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadUserInfo();
  }

  Future<void> _loadUserInfo() async {
    try {
      final userInfo = await _apiService.getUserInfo();
      setState(() {
        _userInfo = userInfo;
        _isLoading = false;
      });
    } catch (e) {
      setState(() {
        _isLoading = false;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Error al cargar información: $e'),
          backgroundColor: Colors.red,
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return Scaffold(
        appBar: AppBar(title: Text('Perfil')),
        body: Center(child: CircularProgressIndicator()),
      );
    }

    if (_userInfo == null) {
      return Scaffold(
        appBar: AppBar(title: Text('Perfil')),
        body: Center(child: Text('No se pudo cargar la información')),
      );
    }

    return Scaffold(
      appBar: AppBar(title: Text('Perfil')),
      body: Padding(
        padding: EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Nombre: ${_userInfo!['nombre']}'),
            Text('Email: ${_userInfo!['email']}'),
            Text('Email Verificado: ${_userInfo!['emailVerificado']}'),
            Text('Quiz Completado: ${_userInfo!['quizCompleted']}'),
          ],
        ),
      ),
    );
  }
}
```

---

## 7. Flujo Completo

```
┌─────────────────────────────────────────────────────────────┐
│ 1. Usuario toca "Iniciar sesión con Google"                │
└───────────────────────┬─────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────────────────┐
│ 2. Google Sign-In muestra selector de cuentas              │
│    (dentro de la app móvil)                                 │
└───────────────────────┬─────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────────────────┐
│ 3. Usuario selecciona su cuenta de Google                   │
└───────────────────────┬─────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────────────────┐
│ 4. Google devuelve access_token                             │
└───────────────────────┬─────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────────────────┐
│ 5. App envía POST /api/auth/google/token                    │
│    Body: { "access_token": "ya29.a0AfH6SMBx..." }          │
└───────────────────────┬─────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────────────────┐
│ 6. Backend procesa y retorna:                               │
│    {                                                        │
│      "success": true,                                       │
│      "data": {                                              │
│        "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",              │
│        "user": { ... }                                      │
│      }                                                      │
│    }                                                        │
└───────────────────────┬─────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────────────────┐
│ 7. App guarda JWT en SharedPreferences                      │
└───────────────────────┬─────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────────────────┐
│ 8. Usuario autenticado ✅                                    │
│    Redirigir a pantalla principal                           │
└─────────────────────────────────────────────────────────────┘
```

---

## 🔧 Configuración del Backend

### Variables de Entorno en Producción

Asegúrate de tener estas variables en tu `.env` de producción:

```env
GOOGLE_CLIENT_ID=tu_client_id_de_produccion
GOOGLE_CLIENT_SECRET=tu_client_secret_de_produccion
GOOGLE_REDIRECT_URI=https://web.estoico.app/api/auth/google/callback
```

### Endpoint Disponible

```
POST /api/auth/google/token
Content-Type: application/json

Body:
{
  "access_token": "ya29.a0AfH6SMBx..."
}

Response (éxito):
{
  "success": true,
  "message": "Login con Google exitoso",
  "data": {
    "user": {
      "id": "...",
      "nombre": "...",
      "email": "...",
      ...
    },
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc..."
  }
}
```

---

## ⚠️ Notas Importantes

1. **URL de la API**: Cambia `apiBaseUrl` en `GoogleAuthService` según tu entorno:
   - Desarrollo local: `http://localhost:8000/api`
   - Producción: `https://web.estoico.app/api`

2. **SHA-1 para Android**: Debes agregar el SHA-1 de tu keystore de producción también (no solo el de debug)

3. **Manejo de Errores**: Implementa manejo de errores robusto, especialmente para:
   - Errores de red
   - Token expirado
   - Usuario no autenticado

4. **Seguridad**: Nunca hardcodees tokens o credenciales en el código. Usa variables de entorno o archivos de configuración seguros.

---

## 📚 Recursos Adicionales

- [Documentación de google_sign_in](https://pub.dev/packages/google_sign_in)
- [Google Cloud Console](https://console.cloud.google.com/)
- [Flutter HTTP Package](https://pub.dev/packages/http)
- [SharedPreferences](https://pub.dev/packages/shared_preferences)

---

## ✅ Checklist de Implementación

- [ ] Dependencias agregadas en `pubspec.yaml`
- [ ] SHA-1 agregado en Google Cloud Console (Android)
- [ ] `GoogleService-Info.plist` agregado en iOS
- [ ] URL scheme configurado en `Info.plist` (iOS)
- [ ] Servicio `GoogleAuthService` creado
- [ ] Pantalla de login implementada
- [ ] Token JWT guardado correctamente
- [ ] Peticiones autenticadas funcionando
- [ ] Probado en dispositivo físico (no solo emulador)

---

**¡Listo para implementar!** 🚀

