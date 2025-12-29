@extends('layouts.app')

@section('title', 'Estado de Suscripción - Stoic App')

@section('styles')
<style>
    body {
        background: #f5f5f5;
    }
    
    .status-container {
        max-width: 600px;
        margin: 2rem auto;
        padding: 0 1rem;
    }
    
    .status-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        overflow: hidden;
        border: 1px solid #e5e7eb;
    }
    
    .status-header {
        padding: 2rem;
        text-align: center;
        background: linear-gradient(135deg, #7e8191ff 0%, #303030ff 100%);
        color: white;
    }
    
    .status-header.inactive {
        background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
    }
    
    .status-icon {
        font-size: 3rem;
        margin-bottom: 1rem;
    }
    
    .status-title {
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
    }
    
    .status-subtitle {
        opacity: 0.9;
        font-size: 0.95rem;
    }
    
    .status-content {
        padding: 2rem;
    }
    
    .info-row {
        display: flex;
        justify-content: space-between;
        padding: 1rem 0;
        border-bottom: 1px solid #f3f4f6;
    }
    
    .info-row:last-child {
        border-bottom: none;
    }
    
    .info-label {
        color: #6b7280;
        font-size: 0.875rem;
        font-weight: 500;
    }
    
    .info-value {
        color: #111827;
        font-weight: 600;
        text-align: right;
    }
    
    .status-badge {
        display: inline-block;
        padding: 0.375rem 0.875rem;
        border-radius: 6px;
        font-size: 0.875rem;
        font-weight: 600;
    }
    
    .status-badge.active {
        background: #d1fae5;
        color: #065f46;
    }
    
    .status-badge.inactive {
        background: #fee2e2;
        color: #991b1b;
    }
    
    .action-buttons {
        padding: 0 2rem 2rem;
        display: flex;
        gap: 1rem;
        flex-direction: column;
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
        display: block;
    }
    
    .btn-primary {
        background: #111827;
        color: white;
    }
    
    .btn-primary:hover {
        background: #374151;
        color: white;
    }
    
    .btn-secondary {
        background: #f3f4f6;
        color: #374151;
    }
    
    .btn-secondary:hover {
        background: #e5e7eb;
        color: #374151;
    }
    
    .btn-danger {
        background: #dc2626;
        color: white;
    }
    
    .btn-danger:hover {
        background: #b91c1c;
        color: white;
    }
    
    .empty-state {
        text-align: center;
        padding: 3rem 2rem;
    }
    
    .empty-icon {
        font-size: 4rem;
        color: #9ca3af;
        margin-bottom: 1rem;
    }
    
    .empty-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: #374151;
        margin-bottom: 0.5rem;
    }
    
    .empty-text {
        color: #6b7280;
        margin-bottom: 2rem;
    }
</style>
@endsection

@section('content')
<div class="status-container">
    <div class="status-card">
        @if($hasSubscription && $subscription)
            <!-- Header con suscripción activa -->
            <div class="status-header {{ $subscription->isActive() ? '' : 'inactive' }}">
                <div class="status-icon">
                    @if($subscription->isActive())
                        <i class="bi bi-check-circle-fill"></i>
                    @else
                        <i class="bi bi-x-circle-fill"></i>
                    @endif
                </div>
                <h1 class="status-title">
                    @if($subscription->isActive())
                        Suscripción Activa
                    @else
                        Suscripción Inactiva
                    @endif
                </h1>
                <p class="status-subtitle">Plan {{ $subscription->plan_name }}</p>
            </div>
            
            <!-- Contenido con detalles -->
            <div class="status-content">
                <div class="info-row">
                    <span class="info-label">Estado</span>
                    <span class="info-value">
                        <span class="status-badge {{ $subscription->isActive() ? 'active' : 'inactive' }}">
                            {{ $subscription->isActive() ? 'Activa' : ucfirst($subscription->status) }}
                        </span>
                    </span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Precio</span>
                    <span class="info-value">${{ number_format($subscription->amount, 2) }} {{ $subscription->currency }}</span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Intervalo de pago</span>
                    <span class="info-value">{{ $subscription->interval === 'month' ? 'Mensual' : 'Anual' }}</span>
                </div>
                
                @if($subscription->current_period_start)
                <div class="info-row">
                    <span class="info-label">Inicio del período</span>
                    <span class="info-value">{{ $subscription->current_period_start->format('d/m/Y') }}</span>
                </div>
                @endif
                
                @if($subscription->current_period_end)
                <div class="info-row">
                    <span class="info-label">Fin del período</span>
                    <span class="info-value">{{ $subscription->current_period_end->format('d/m/Y') }}</span>
                </div>
                @endif
                
                @if($subscription->ends_at)
                <div class="info-row">
                    <span class="info-label">Finaliza el</span>
                    <span class="info-value">{{ $subscription->ends_at->format('d/m/Y') }}</span>
                </div>
                @endif
            </div>
            
            <!-- Botones de acción -->
            <div class="action-buttons">
                @if($subscription->isActive())
                    <form action="{{ route('subscription.cancel') }}" method="POST" onsubmit="return confirm('¿Estás seguro de que deseas cancelar tu suscripción?')">
                        @csrf
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-x-circle"></i> Cancelar suscripción
                        </button>
                    </form>
                @else
                    <a href="{{ route('subscription.premium') }}" class="btn btn-primary">
                        <i class="bi bi-arrow-repeat"></i> Renovar suscripción
                    </a>
                @endif
                
                <a href="/" class="btn btn-secondary">
                    <i class="bi bi-house"></i> Volver al inicio
                </a>
            </div>
        @else
            <!-- Estado sin suscripción -->
            <div class="status-header inactive">
                <div class="status-icon">
                    <i class="bi bi-info-circle"></i>
                </div>
                <h1 class="status-title">Sin Suscripción</h1>
                <p class="status-subtitle">Descubre los beneficios Premium</p>
            </div>
            
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="bi bi-crown"></i>
                </div>
                <h3 class="empty-title">No tienes una suscripción activa</h3>
                <p class="empty-text">Desbloquea todas las funciones premium y lleva tu experiencia al siguiente nivel</p>
                
                <a href="{{ route('subscription.premium') }}" class="btn btn-primary">
                    <i class="bi bi-star-fill"></i> Ver planes Premium
                </a>
            </div>
        @endif
    </div>
</div>
@endsection
