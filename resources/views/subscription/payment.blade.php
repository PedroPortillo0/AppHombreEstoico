@extends('layouts.app')

@section('title', 'Pago Suscripción - Stoic App')

@section('head')
<!-- jQuery necesario para Openpay -->
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<!-- Openpay Scripts -->
<script type="text/javascript" src="https://resources.openpay.mx/lib/openpay-js/1.2.38/openpay.v1.min.js"></script>
<script type="text/javascript" src="https://resources.openpay.mx/lib/openpay-data-js/1.2.38/openpay-data.v1.min.js"></script>
@endsection

@section('styles')
<style>
    body {
        background: #f5f5f5;
    }
    
    .payment-container {
        max-width: 500px;
        margin: 2rem auto;
        padding: 0 1rem;
    }
    
    .payment-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        overflow: hidden;
        border: 1px solid #e5e7eb;
    }
    
    .payment-header {
        padding: 2rem;
        text-align: center;
        background: #f9fafb;
        border-bottom: 1px solid #e5e7eb;
    }
    
    .back-link {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        color: #6b7280;
        text-decoration: none;
        font-size: 0.875rem;
        margin-bottom: 1rem;
    }
    
    .back-link:hover {
        color: #111827;
    }
    
    .payment-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: #111827;
        margin-bottom: 0.5rem;
    }
    
    .payment-subtitle {
        color: #6b7280;
        font-size: 0.875rem;
    }
    
    .price-display {
        font-size: 2rem;
        font-weight: 700;
        color: #111827;
        margin-top: 1rem;
    }
    
    .payment-form {
        padding: 2rem;
    }
    
    .form-group {
        margin-bottom: 1.5rem;
    }
    
    .form-label {
        display: block;
        font-weight: 600;
        color: #374151;
        margin-bottom: 0.5rem;
        font-size: 0.875rem;
    }
    
    .form-control {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: 1rem;
        transition: all 0.2s ease;
    }
    
    .form-control:focus {
        outline: none;
        border-color: #1f2937;
        box-shadow: 0 0 0 3px rgba(31, 41, 55, 0.1);
    }
    
    .form-control::placeholder {
        color: #9ca3af;
    }
    
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 1rem;
    }
    
    .card-icon {
        position: absolute;
        right: 1rem;
        top: 50%;
        transform: translateY(-50%);
        font-size: 1.5rem;
        color: #9ca3af;
    }
    
    .input-wrapper {
        position: relative;
    }
    
    .submit-btn {
        background: #1f2937;
        border: none;
        color: white;
        padding: 1rem;
        font-size: 1rem;
        font-weight: 600;
        border-radius: 8px;
        width: 100%;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }
    
    .submit-btn:hover:not(:disabled) {
        background: #374151;
        transform: translateY(-1px);
    }
    
    .submit-btn:disabled {
        background: #9ca3af;
        cursor: not-allowed;
    }
    
    .spinner {
        width: 1rem;
        height: 1rem;
        border: 2px solid #ffffff;
        border-top-color: transparent;
        border-radius: 50%;
        animation: spin 0.6s linear infinite;
    }
    
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    
    .security-note {
        text-align: center;
        color: #6b7280;
        font-size: 0.75rem;
        margin-top: 1rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }
    
    .card-brands {
        display: flex;
        justify-content: center;
        gap: 1rem;
        margin-top: 1.5rem;
        padding-top: 1.5rem;
        border-top: 1px solid #e5e7eb;
        font-size: 1.5rem;
        color: #9ca3af;
    }
    
    .alert {
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
        display: none;
    }
    
    .alert.show {
        display: block;
    }
    
    .alert-success {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #6ee7b7;
    }
    
    .alert-error {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fca5a5;
    }
    
    .form-help {
        font-size: 0.75rem;
        color: #6b7280;
        margin-top: 0.25rem;
    }
</style>
@endsection

@section('content')
<div class="payment-container">
    <div class="payment-card">
        <!-- Header -->
        <div class="payment-header">
            <a href="{{ route('subscription.premium') }}" class="back-link">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
            <h1 class="payment-title">Suscripción Premium</h1>
            <p class="payment-subtitle">Completa tu información de pago</p>
            <div class="price-display">$99.99 <span style="font-size: 1rem; font-weight: 400;">/mes</span></div>
        </div>
        
        <!-- Form -->
        <div class="payment-form">
            <!-- Alerts -->
            <div id="successAlert" class="alert alert-success">
                <i class="bi bi-check-circle"></i> <span id="successMessage"></span>
            </div>
            
            <div id="errorAlert" class="alert alert-error">
                <i class="bi bi-exclamation-circle"></i> <span id="errorMessage"></span>
            </div>
            
            <form id="paymentForm">
                @csrf
                
                <!-- Card Number -->
                <div class="form-group">
                    <label class="form-label" for="card_number">
                        Número de Tarjeta
                    </label>
                    <div class="input-wrapper">
                        <input 
                            type="text" 
                            class="form-control" 
                            id="card_number" 
                            name="card_number" 
                            placeholder="1234 5678 9012 3456"
                            maxlength="19"
                            required
                            autocomplete="cc-number"
                        >
                    </div>
                </div>
                
                <!-- Card Holder -->
                <div class="form-group">
                    <label class="form-label" for="holder_name">
                        Nombre del Titular
                    </label>
                    <input 
                        type="text" 
                        class="form-control" 
                        id="holder_name" 
                        name="holder_name" 
                        placeholder="Como aparece en la tarjeta"
                        required
                        autocomplete="cc-name"
                    >
                    <div class="form-help">Nombre completo del titular de la tarjeta</div>
                </div>
                
                <!-- Expiration & CVV -->
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="expiration_month">
                            Mes
                        </label>
                        <input 
                            type="text" 
                            class="form-control" 
                            id="expiration_month" 
                            name="expiration_month" 
                            placeholder="MM"
                            maxlength="2"
                            required
                            autocomplete="cc-exp-month"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="expiration_year">
                            Año
                        </label>
                        <input 
                            type="text" 
                            class="form-control" 
                            id="expiration_year" 
                            name="expiration_year" 
                            placeholder="YY"
                            maxlength="2"
                            required
                            autocomplete="cc-exp-year"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="cvv2">
                            CVV
                        </label>
                        <input 
                            type="text" 
                            class="form-control" 
                            id="cvv2" 
                            name="cvv2" 
                            placeholder="123"
                            maxlength="4"
                            required
                            autocomplete="cc-csc"
                        >
                    </div>
                </div>
                
                <!-- Submit Button -->
                <button type="submit" class="submit-btn" id="submitBtn">
                    <span id="btnText">Confirmar Pago</span>
                    <div id="btnSpinner" class="spinner" style="display: none;"></div>
                </button>
                
                <div class="security-note">
                    <i class="bi bi-lock-fill"></i>
                    Pago seguro procesado por OpenPay
                </div>
                
                <!-- Card Brands -->
                <div class="card-brands">
                    <i class="bi bi-credit-card-2-front"></i>
                    <span>💳</span>
                    <span>🏦</span>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    // Configuración
    const API_URL = '{{ rtrim(config("app.url"), "/") }}';
    
    // Obtener token de URL o sesión
    const urlParams = new URLSearchParams(window.location.search);
    const JWT_TOKEN = urlParams.get('token') || '{{ session("jwt_token") ?? "" }}';
    
    console.log('API URL:', API_URL);
    console.log('Token disponible:', JWT_TOKEN ? 'Sí' : 'No');
    
    // Elementos del DOM
    const form = document.getElementById('paymentForm');
    const submitBtn = document.getElementById('submitBtn');
    const btnText = document.getElementById('btnText');
    const btnSpinner = document.getElementById('btnSpinner');
    const successAlert = document.getElementById('successAlert');
    const errorAlert = document.getElementById('errorAlert');
    const successMessage = document.getElementById('successMessage');
    const errorMessage = document.getElementById('errorMessage');
    
    // Formatear número de tarjeta
    document.getElementById('card_number').addEventListener('input', function(e) {
        let value = e.target.value.replace(/\s/g, '');
        let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
        e.target.value = formattedValue;
    });
    
    // Solo números en campos numéricos
    ['card_number', 'expiration_month', 'expiration_year', 'cvv2'].forEach(fieldId => {
        document.getElementById(fieldId).addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '');
        });
    });
    
    // Manejo del formulario
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        // Ocultar alertas
        successAlert.classList.remove('show');
        errorAlert.classList.remove('show');
        
        // Deshabilitar botón
        submitBtn.disabled = true;
        btnText.textContent = 'Procesando...';
        btnSpinner.style.display = 'block';
        
        try {
            // Recopilar datos
            const formData = {
                card_number: document.getElementById('card_number').value.replace(/\s/g, ''),
                holder_name: document.getElementById('holder_name').value,
                expiration_month: document.getElementById('expiration_month').value.padStart(2, '0'),
                expiration_year: document.getElementById('expiration_year').value,
                cvv2: document.getElementById('cvv2').value
            };
            
            // Validar datos
            if (!validateForm(formData)) {
                throw new Error('Por favor completa todos los campos correctamente');
            }
            
            // Llamar a la API
            const response = await fetch(`${API_URL}/api/subscriptions`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${JWT_TOKEN}`,
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                credentials: 'same-origin',
                body: JSON.stringify(formData)
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Éxito
                successMessage.textContent = data.message || '¡Suscripción creada exitosamente!';
                successAlert.classList.add('show');
                
                // Limpiar formulario
                form.reset();
                
                // Redirigir después de 2 segundos
                setTimeout(() => {
                    window.location.href = '{{ route("subscription.status") }}';
                }, 2000);
            } else {
                // Error del servidor
                throw new Error(data.message || 'Error al procesar la suscripción');
            }
            
        } catch (error) {
            console.error('Error:', error);
            errorMessage.textContent = error.message;
            errorAlert.classList.add('show');
        } finally {
            // Rehabilitar botón
            submitBtn.disabled = false;
            btnText.textContent = 'Confirmar Pago';
            btnSpinner.style.display = 'none';
        }
    });
    
    // Validación
    function validateForm(data) {
        // Validar número de tarjeta
        if (data.card_number.length < 13 || data.card_number.length > 19) {
            return false;
        }
        
        // Validar mes
        const month = parseInt(data.expiration_month);
        if (month < 1 || month > 12) {
            return false;
        }
        
        // Validar año
        const currentYear = new Date().getFullYear() % 100;
        const year = parseInt(data.expiration_year);
        if (year < currentYear) {
            return false;
        }
        
        // Validar CVV
        if (data.cvv2.length < 3 || data.cvv2.length > 4) {
            return false;
        }
        
        return true;
    }
    
    // Verificar si el usuario ya tiene suscripción al cargar
    async function checkExistingSubscription() {
        if (!JWT_TOKEN) {
            console.warn('No hay token JWT disponible');
            errorMessage.textContent = 'Debes iniciar sesión para suscribirte';
            errorAlert.classList.add('show');
            submitBtn.disabled = true;
            return;
        }
        
        try {
            const response = await fetch(`${API_URL}/api/subscriptions/status`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${JWT_TOKEN}`
                },
                credentials: 'same-origin'
            });
            
            if (!response.ok) {
                console.error('Error en respuesta:', response.status, response.statusText);
                return;
            }
            
            const data = await response.json();
            
            if (data.has_subscription && data.subscription && data.subscription.is_active) {
                successMessage.textContent = 'Ya tienes una suscripción activa';
                successAlert.classList.add('show');
                submitBtn.disabled = true;
                
                setTimeout(() => {
                    window.location.href = '{{ route("subscription.status") }}';
                }, 2000);
            }
        } catch (error) {
            console.error('Error verificando suscripción:', error);
            // No mostramos error al usuario, solo en consola
        }
    }
    
    // Verificar al cargar la página
    checkExistingSubscription();
</script>
@endsection
