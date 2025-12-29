@extends('layouts.app')

@section('title', 'Plan Premium - Stoic App')

@section('styles')
<style>
    body {
        background: #f5f5f5;
    }
    
    .premium-container {
        max-width: 600px;
        margin: 0 auto;
        padding: 2rem 1rem;
    }
    
    .premium-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        overflow: hidden;
        border: 1px solid #e5e7eb;
    }
    
    .badge-premium {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        background: #f3f4f6;
        color: #6b7280;
        padding: 0.5rem 1rem;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 500;
        margin-bottom: 1.5rem;
    }
    
    .badge-premium i {
        color: #9ca3af;
    }
    
    .premium-content {
        padding: 2.5rem;
        text-align: center;
    }
    
    .premium-title {
        font-size: 1.875rem;
        font-weight: 700;
        color: #111827;
        margin-bottom: 0.5rem;
    }
    
    .premium-subtitle {
        color: #6b7280;
        font-size: 0.95rem;
        margin-bottom: 2.5rem;
    }
    
    .price-section {
        margin-bottom: 2.5rem;
    }
    
    .original-price {
        color: #9ca3af;
        text-decoration: line-through;
        font-size: 1.125rem;
        margin-right: 0.5rem;
    }
    
    .price {
        font-size: 3.5rem;
        font-weight: 700;
        color: #111827;
        line-height: 1;
    }
    
    .price-period {
        color: #6b7280;
        font-size: 1.125rem;
    }
    
    .discount-badge {
        display: inline-block;
        background: #f3f4f6;
        color: #374151;
        padding: 0.375rem 0.875rem;
        border-radius: 6px;
        font-size: 0.875rem;
        margin-top: 0.75rem;
    }
    
    .features-section {
        text-align: left;
        padding: 0 2.5rem 2.5rem;
    }
    
    .features-title {
        font-size: 1.125rem;
        font-weight: 600;
        color: #111827;
        margin-bottom: 1.5rem;
    }
    
    .feature-item {
        display: flex;
        align-items: flex-start;
        padding: 1rem 0;
        gap: 1rem;
    }
    
    .feature-checkbox {
        width: 24px;
        height: 24px;
        background: #1f2937;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        margin-top: 0.125rem;
    }
    
    .feature-checkbox i {
        color: white;
        font-size: 0.75rem;
    }
    
    .feature-icon {
        color: #6b7280;
        font-size: 1.25rem;
        margin-right: 0.5rem;
        flex-shrink: 0;
    }
    
    .feature-content {
        flex: 1;
    }
    
    .feature-title {
        font-size: 1rem;
        font-weight: 600;
        color: #111827;
        margin-bottom: 0.25rem;
    }
    
    .feature-description {
        color: #6b7280;
        font-size: 0.875rem;
        margin: 0;
    }
    
    .subscribe-btn {
        background: #1f2937;
        border: none;
        color: white;
        padding: 1rem 2rem;
        font-size: 1rem;
        font-weight: 500;
        border-radius: 8px;
        width: 100%;
        transition: all 0.2s ease;
        margin-bottom: 1rem;
    }
    
    .subscribe-btn:hover {
        background: #374151;
        transform: translateY(-1px);
    }
    
    .cancel-text {
        text-align: center;
        color: #9ca3af;
        font-size: 0.875rem;
        margin-bottom: 2rem;
    }
    
    .security-badges {
        display: flex;
        justify-content: center;
        gap: 2rem;
        flex-wrap: wrap;
        padding: 1.5rem 2.5rem;
        border-top: 1px solid #e5e7eb;
    }
    
    .security-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: #6b7280;
        font-size: 0.875rem;
    }
    
    .security-item i {
        font-size: 1rem;
    }
    
    @media (max-width: 768px) {
        .premium-content {
            padding: 2rem 1.5rem;
        }
        
        .features-section {
            padding: 0 1.5rem 1.5rem;
        }
        
        .price {
            font-size: 2.75rem;
        }
        
        .security-badges {
            gap: 1rem;
            padding: 1.5rem;
        }
    }
</style>
@endsection

@section('content')
<div class="premium-container">
    <div class="premium-card">
        <!-- Content Section -->
        <div class="premium-content">
            <!-- Mensaje de error si existe -->
            @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                <i class="bi bi-exclamation-circle"></i> {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            @endif
            
            <!-- Mensaje de éxito si existe -->
            @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                <i class="bi bi-check-circle"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            @endif
            
            <div class="text-end">
                <span class="badge-premium">
                    <i class="bi bi-crown"></i> Plan Premium
                </span>
            </div>
            
            <h1 class="premium-title">Mejora tu experiencia</h1>
            <p class="premium-subtitle">Desbloquea beneficios exclusivos y lleva tu cuenta al siguiente nivel</p>
            
            <!-- Price Section -->
            <div class="price-section">
                <div class="mb-2">
                    <span class="original-price">$250</span>
                    <span class="price">$99.99</span>
                    <span class="price-period">/mes</span>
                </div>
                <span class="discount-badge">60% de descuento</span>
            </div>
        </div>
        
        <!-- Features Section -->
        <div class="features-section">
            <h4 class="features-title">Todo lo que incluye:</h4>
            
            <div class="feature-item">
                <div class="feature-checkbox">
                    <i class="bi bi-check-lg"></i>
                </div>
                <span class="feature-icon">
                    <i class="bi bi-stars"></i>
                </span>
                <div class="feature-content">
                    <div class="feature-title">Frase personalizada</div>
                    <p class="feature-description">Crea y personaliza tu mensaje único</p>
                </div>
            </div>
            
            <div class="feature-item">
                <div class="feature-checkbox">
                    <i class="bi bi-check-lg"></i>
                </div>
                <span class="feature-icon">
                    <i class="bi bi-award"></i>
                </span>
                <div class="feature-content">
                    <div class="feature-title">Insignia de perfil Premium</div>
                    <p class="feature-description">Destaca con tu badge exclusivo</p>
                </div>
            </div>
            
            <div class="feature-item">
                <div class="feature-checkbox">
                    <i class="bi bi-check-lg"></i>
                </div>
                <span class="feature-icon">
                    <i class="bi bi-shield"></i>
                </span>
                <div class="feature-content">
                    <div class="feature-title">Soporte prioritario</div>
                    <p class="feature-description">Atención preferencial 24/7</p>
                </div>
            </div>
            
            <div class="feature-item">
                <div class="feature-checkbox">
                    <i class="bi bi-check-lg"></i>
                </div>
                <span class="feature-icon">
                    <i class="bi bi-lightning-charge"></i>
                </span>
                <div class="feature-content">
                    <div class="feature-title">Acceso anticipado</div>
                    <p class="feature-description">Primero en nuevas funcionalidades</p>
                </div>
            </div>
            
            <!-- Subscribe Button -->
            <div class="mt-4">
                <a href="{{ route('subscription.payment') }}" class="btn subscribe-btn text-white text-decoration-none d-block">
                    Suscribirme ahora
                </a>
                <p class="cancel-text">
                    Cancela en cualquier momento. Sin compromisos.
                </p>
            </div>
        </div>
        
        <!-- Security Badges -->
        <div class="security-badges">
            <div class="security-item">
                <i class="bi bi-shield-check"></i>
                <span>Pago seguro</span>
            </div>
            <div class="security-item">
                <i class="bi bi-x-circle"></i>
                <span>Sin permanencia</span>
            </div>
            <div class="security-item">
                <i class="bi bi-lightning-charge"></i>
                <span>Activación inmediata</span>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    // Agregar animaciones o lógica adicional aquí si es necesario
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Vista Premium cargada');
    });
</script>
@endsection
