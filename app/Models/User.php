<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLE_MASTER = 'master';
    public const ROLE_CLIENT = 'client';
    public const ROLE_ADMIN  = 'admin';

    protected $fillable = [
        'role',
        'name',
        'slug',
        'email',
        'phone',
        'phone_normalized',
        'phone2',
        'phone2_normalized',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    protected static function booted(): void
    {
        // Автоматически нормализуем телефон при сохранении
        static::saving(function (User $user) {
            if ($user->isDirty('phone') || $user->phone_normalized === null) {
                $user->phone_normalized = self::normalizePhone($user->phone);
            }
            if ($user->isDirty('phone2') || $user->phone2_normalized === null) {
                $user->phone2_normalized = self::normalizePhone($user->phone2);
            }

            // Автогенерация slug для мастеров при регистрации
            if ($user->role === self::ROLE_MASTER && empty($user->slug)) {
                $user->slug = self::generateUniqueSlug($user->name);
            }
        });
    }

    // =========================
    // Roles
    // =========================

    public function isMaster(): bool
    {
        return $this->role === self::ROLE_MASTER;
    }

    public function isClient(): bool
    {
        return $this->role === self::ROLE_CLIENT;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    // =========================
    // Relations — мастер
    // =========================

    /** Услуги мастера */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'user_id');
    }

    /** Записи, которые мастер ведёт */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'master_id');
    }

    /** Карточки клиентов у мастера */
    public function clientCards(): HasMany
    {
        return $this->hasMany(Client::class, 'master_id');
    }

    /** Транзакции мастера */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'user_id');
    }

    /** Push-подписки */
    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class, 'user_id');
    }

    // =========================
    // Relations — клиент
    // =========================

    /** Карточки клиента у разных мастеров */
    public function myClientCards(): HasMany
    {
        return $this->hasMany(Client::class, 'client_user_id');
    }

    /** Мастера, к которым привязан клиент */
    public function myMasters(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'clients',
            'client_user_id',   // foreign pivot key
            'master_id'         // related pivot key
        )
            ->withPivot(['id', 'name', 'phone', 'contact', 'is_permanent'])
            ->withTimestamps();
    }

    /** Записи, которые клиент сделал (у всех мастеров) */
    public function myAppointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'client_user_id');
    }

    // =========================
    // Helpers
    // =========================

    public static function normalizePhone(?string $phone): ?string
    {
        if ($phone === null || $phone === '') {
            return null;
        }
        $digits = preg_replace('/\D+/u', '', $phone);
        if ($digits === '') {
            return null;
        }
        if (strlen($digits) === 11 && $digits[0] === '8') {
            $digits = '7' . substr($digits, 1);
        }
        return $digits;
    }

    public static function generateUniqueSlug(string $name, ?int $excludeUserId = null): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'master-' . ($excludeUserId ?? random_int(1000, 9999));
        }

        $slug = $base;
        $i = 2;
        while (
        self::where('slug', $slug)
            ->when($excludeUserId, fn ($q) => $q->where('id', '!=', $excludeUserId))
            ->exists()
        ) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
