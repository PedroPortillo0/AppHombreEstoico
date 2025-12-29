<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use HasFactory;

    protected $table = 'users';
    protected $primaryKey = 'id';
    public $incrementing = false; // UUID como primary key
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'nombre',
        'apellidos',
        'email',
        'password',
        'email_verificado',
        'quiz_completed',
        'google_id',
        'avatar',
        'auth_provider',
        'is_admin',
        'stoic_points',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verificado' => 'boolean',
            'quiz_completed' => 'boolean',
            'is_admin' => 'boolean',
            'stoic_points' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Relación con suscripciones
     */
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Obtener la suscripción activa del usuario
     */
    public function activeSubscription()
    {
        return $this->hasOne(Subscription::class)
            ->where('status', 'active')
            ->whereNull('ends_at')
            ->orWhere('ends_at', '>', now());
    }

    /**
     * Verificar si el usuario tiene una suscripción activa
     */
    public function hasActiveSubscription(): bool
    {
        return $this->subscriptions()
            ->where('status', 'active')
            ->where(function($query) {
                $query->whereNull('ends_at')
                      ->orWhere('ends_at', '>', now());
            })
            ->exists();
    }

    /**
     * Verificar si el usuario tiene acceso a frases personalizadas
     * Requiere: quiz completado Y suscripción activa
     */
    public function canAccessPersonalizedQuotes(): bool
    {
        return $this->quiz_completed && $this->hasActiveSubscription();
    }
}