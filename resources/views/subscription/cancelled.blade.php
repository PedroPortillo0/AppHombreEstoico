@extends('layouts.app')

@section('title', 'Suscripción Cancelada - Stoic App')

@section('styles')
<style>
    body {
        background: #f5f5f5;
    }
    
    .cancelled-container {
        max-width: 600px;
        margin: 3rem auto;
        padding: 0 1rem;
    }
    
    .cancelled-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        overflow: hidden;
        border: 1px solid #e5e7eb;
        animation: slideIn 0.3s ease-out;
    }
    
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .cancelled-header {
        padding: 3rem 2rem 2rem;
        text-align: center;
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        color: white;
        position: relative;
    }
    
    .cancelled-icon {
        width: 80px;
        height: 80px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.5rem;
        font-size: 2.5rem;
    }
    
    .cancelled-icon i {
        animation: scaleIn 0.5s ease-out;
    }
    
    @keyframes scaleIn {
        from {
            transform: scale(0);
        }
        to {
            transform: scale(1);
        }
    }
    
    .cancelled-title {
        font-size: 1.75rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
    }
    
    .cancelled-subtitle {
        font-size: 1rem;
        opacity: 0.9;
        line-height: 1.5;
    }
    
    .cancelled-content {
        padding: 2rem;
    }
    
    .info-box {
        background: #fef3c7;
        border: 1px solid #fbbf24;
        border-radius: 8px;
        padding: 1rem;
        margin-bottom: 1.5rem;
    }
    
    .info-box-title {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-weight: 600;
        color: #92400e;
        margin-bottom: 0.5rem;
    }
    
    .info-box-text {
        color: #78350f;
        font-size: 0.875rem;
        line-height: 1.5;
    }
    
    .details-section {
        margin-bottom: 2rem;
    }
    
    .details-title {
        font-size: 1rem;
        font-weight: 600;
        color: #374151;
        margin-bottom: 1rem;
    }
    
    .detail-row {
        display: flex;
        justify-content: space-between;
        padding: 0.75rem 0;
        border-bottom: 1px solid #f3f4f6;
    }
    
    .detail-row:last-child {
        border-bottom: none;
    }
    
    .detail-label {
        color: #6b7280;
        font-size: 0.875rem;
    }
    
    .detail-value {
        color: #111827;
        font-weight: 600;
        text-align: right;
    }
    
    .highlight-box {
        background: #f0fdf4;
        border: 1px solid #86efac;
        border-radius: 8px;
        padding: 1rem;
        margin-bottom: 2rem;
        text-align: center;
    }
    
    .highlight-text {
        color: #166534;
        font-weight: 600;
        font-size: 0.95rem;
    }
    
    .highlight-date {
        color: #15803d;
        font-size: 1.25rem;
        font-weight: 700;
        margin-top: 0.5rem;
    }
    
    .action-buttons {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    
    .btn {
        padding: 0.875rem 1.5rem;
        border-radius: 8px;
        font-weight: 600;
        text-align: center;
        text-decoration: none;
        border: none;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }
    
    .btn-primary {
        background: #111827;
        color: white;
    }
    
    .btn-primary:hover {
        background: #374151;
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
    
    .btn-secondary {
        background: #f3f4f6;
        color: #374151;
    }
    
    .btn-secondary:hover {
        background: #e5e7eb;
        color: #374151;
    }
    
    .feedback-section {
        margin-top: 2rem;
        padding-top: 2rem;
        border-top: 1px solid #e5e7eb;
        text-align: center;
    }
    
    .feedback-text {
        color: #6b7280;
        font-size: 0.875rem;
        margin-bottom: 1rem;
    }
    
    .feedback-link {
        color: #2563eb;
        text-decoration: none;
        font-weight: 500;
    }
    
    .feedback-link:hover {
        text-decoration: underline;
    }
    
    @media (max-width: 768px) {
        .cancelled-container {
            margin: 2rem auto;
        }
        
        .cancelled-header {
            padding: 2rem 1.5rem 1.5rem;
        }
        
        .cancelled-content {
            padding: 1.5rem;
        }
    }
</style>
@endsection

@section('content')
<div class="cancelled-container">
    <div class="cancelled-card">
        <!-- Header -->
        <div class="cancelled-header">
            <div class="cancelled-icon">
                <i class="bi bi-check-circle-fill"></i>
            </div>
            <h1 class="cancelled-title">Suscripción Cancelada</h1>
            <p class="cancelled-subtitle">Tu suscripción Premium ha sido cancelada exitosamente</p>
        </div>
        
        <!-- Content -->
        <div class="cancelled-content">
            <!-- Aviso importante -->
            <div class="info-box">
                <div class="info-box-title">
                    <i class="bi bi-info-circle-fill"></i>
                    <span>¡Importante!</span>
                </div>
                <p class="info-box-text">
                    Aunque hayas cancelado, seguirás teniendo acceso a todas las funciones Premium hasta el final de tu período de facturación actual.
                </p>
            </div>
            
            <!-- Acceso hasta -->
            @if($subscription->ends_at)
            <div class="highlight-box">
                <div class="highlight-text">
                    <i class="bi bi-calendar-check"></i> Tendrás acceso Premium hasta:
                </div>
                <div class="highlight-date">
                    {{ $subscription->ends_at->format('d/m/Y') }}
                </div>
                <div style="color: #166534; font-size: 0.875rem; margin-top: 0.5rem;">
                    ({{ $subscription->ends_at->diffForHumans() }})
                </div>
            </div>
            @endif
            
            <!-- Detalles de la suscripción -->
            <div class="details-section">
                <h3 class="details-title">Detalles de la suscripción cancelada</h3>
                
                <div class="detail-row">
                    <span class="detail-label">Plan</span>
                    <span class="detail-value">{{ $subscription->plan_name ?? 'Premium' }}</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Precio</span>
                    <span class="detail-value">${{ number_format($subscription->amount, 2) }} {{ $subscription->currency }}</span>
                </div>
                
                @if($subscription->current_period_start)
                <div class="detail-row">
                    <span class="detail-label">Inicio del período</span>
                    <span class="detail-value">{{ $subscription->current_period_start->format('d/m/Y') }}</span>
                </div>
                @endif
                
                @if($subscription->current_period_end)
                <div class="detail-row">
                    <span class="detail-label">Fin del período</span>
                    <span class="detail-value">{{ $subscription->current_period_end->format('d/m/Y') }}</span>
                </div>
                @endif
                
                @if($subscription->cancelled_at)
                <div class="detail-row">
                    <span class="detail-label">Fecha de cancelación</span>
                    <span class="detail-value">{{ $subscription->cancelled_at->format('d/m/Y H:i') }}</span>
                </div>
                @endif
            </div>
            
            <!-- Botones de acción -->
            <div class="action-buttons">
                <a href="{{ route('subscription.status') }}" class="btn btn-primary">
                    <i class="bi bi-eye"></i>
                    Ver estado de suscripción
                </a>
                
                <a href="/" class="btn btn-secondary">
                    <i class="bi bi-house"></i>
                    Volver al inicio
                </a>
            </div>
            
            <!-- Feedback -->
            <div class="feedback-section">
                <p class="feedback-text">
                    ¿Cambiaste de opinión? Puedes renovar tu suscripción en cualquier momento
                </p>
                <a href="{{ route('subscription.premium') }}" class="feedback-link">
                    <i class="bi bi-arrow-repeat"></i> Renovar suscripción Premium
                </a>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Vista de cancelación cargada');
        
        // Opcional: Mostrar confetti o animación de confirmación
        // confetti({ particleCount: 100, spread: 70, origin: { y: 0.6 } });
    });
</script>
@endsection
