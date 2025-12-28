<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'openpay_customer_id',
        'openpay_subscription_id',
        'openpay_plan_id',
        'openpay_card_id',
        'plan_name',
        'amount',
        'currency',
        'interval',
        'status',
        'trial_start',
        'trial_end',
        'current_period_start',
        'current_period_end',
        'cancelled_at',
        'ends_at',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'trial_start' => 'datetime',
        'trial_end' => 'datetime',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'cancelled_at' => 'datetime',
        'ends_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Relación con el usuario
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Verificar si la suscripción está activa
     */
    public function isActive(): bool
    {
        return $this->status === 'active' && 
               (!$this->ends_at || $this->ends_at->isFuture());
    }

    /**
     * Verificar si la suscripción está en período de prueba
     */
    public function onTrial(): bool
    {
        return $this->status === 'trial' && 
               $this->trial_end && 
               $this->trial_end->isFuture();
    }

    /**
     * Verificar si la suscripción está cancelada
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled' || 
               ($this->ends_at && $this->ends_at->isPast());
    }

    /**
     * Verificar si la suscripción está vencida
     */
    public function isPastDue(): bool
    {
        return $this->status === 'past_due';
    }

    /**
     * Obtener días restantes del período actual
     */
    public function daysUntilRenewal(): int
    {
        if (!$this->current_period_end) {
            return 0;
        }

        return Carbon::now()->diffInDays($this->current_period_end, false);
    }

    /**
     * Obtener días restantes del trial
     */
    public function daysOfTrialRemaining(): int
    {
        if (!$this->onTrial()) {
            return 0;
        }

        return Carbon::now()->diffInDays($this->trial_end, false);
    }

    /**
     * Marcar la suscripción como cancelada
     */
    public function markAsCancelled(): void
    {
        $this->update([
            'status' => 'cancelled',
            'cancelled_at' => Carbon::now(),
            'ends_at' => Carbon::now(),
        ]);
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
                    ->where(function($q) {
                        $q->whereNull('ends_at')
                          ->orWhere('ends_at', '>', Carbon::now());
                    });
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled')
                    ->orWhere('ends_at', '<=', Carbon::now());
    }

    public function scopeOnTrial($query)
    {
        return $query->where('status', 'trial')
                    ->where('trial_end', '>', Carbon::now());
    }

    public function scopePastDue($query)
    {
        return $query->where('status', 'past_due');
    }

    /**
     * Obtener información resumida de la suscripción
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'is_active' => $this->isActive(),
            'on_trial' => $this->onTrial(),
            'is_cancelled' => $this->isCancelled(),
            'days_until_renewal' => $this->daysUntilRenewal(),
            'days_of_trial_remaining' => $this->daysOfTrialRemaining(),
        ]);
    }
}
