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

    public const INVITE_TOKEN_LENGTH   = 8;
    public const INVITE_TOKEN_ALPHABET = '23456789abcdefghjkmnpqrstuvwxyz';

    protected $fillable = [
        'role',
        'name',
        'slug',
        'invite_token',
        'work_hours',
        'slot_step',
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
        'work_hours' => 'array',
        'slot_step' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (User $user) {
            if ($user->isDirty('phone') || $user->phone_normalized === null) {
                $user->phone_normalized = self::normalizePhone($user->phone);
            }
            if ($user->isDirty('phone2') || $user->phone2_normalized === null) {
                $user->phone2_normalized = self::normalizePhone($user->phone2);
            }

            if ($user->role === self::ROLE_MASTER && empty($user->slug)) {
                $user->slug = self::generateUniqueSlug($user->name, $user->id);
            }

            if ($user->role === self::ROLE_MASTER && empty($user->invite_token)) {
                $user->invite_token = self::generateUniqueInviteToken();
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

    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'user_id');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'master_id');
    }

    public function clientCards(): HasMany
    {
        return $this->hasMany(Client::class, 'master_id');
    }

    /**
     * @deprecated Используйте clientCards().
     */
    public function clients(): HasMany
    {
        return $this->clientCards();
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'user_id');
    }

    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class, 'user_id');
    }

    // =========================
    // Relations — клиент
    // =========================

    public function myClientCards(): HasMany
    {
        return $this->hasMany(Client::class, 'client_user_id');
    }

    public function myMasters(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'clients',
            'client_user_id',
            'master_id'
        )
            ->withPivot(['id', 'name', 'phone', 'contact', 'is_permanent'])
            ->withTimestamps();
    }

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
        if ($digits === '' || strlen($digits) < 10) {
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

    /**
     * Уникальный токен для публичной ссылки мастера.
     * 8 символов из 30-буквенного алфавита (без 0, O, 1, l, i).
     * 30^8 ≈ 6.5×10^11 вариантов — перебрать невозможно.
     */
    public static function generateUniqueInviteToken(): string
    {
        $alphabet = self::INVITE_TOKEN_ALPHABET;
        $max = strlen($alphabet) - 1;

        do {
            $token = '';
            for ($i = 0; $i < self::INVITE_TOKEN_LENGTH; $i++) {
                $token .= $alphabet[random_int(0, $max)];
            }
        } while (self::where('invite_token', $token)->exists());

        return $token;
    }

    /**
     * Обновить токен — старая ссылка перестанет работать.
     */
    public function regenerateInviteToken(): void
    {
        $this->invite_token = self::generateUniqueInviteToken();
        $this->save();
    }

    /**
     * Полный URL публичной страницы мастера с токеном.
     */
    public function publicPageUrl(): ?string
    {
        if (!$this->slug || !$this->invite_token) {
            return null;
        }

        return route('master.public', [
            'slug'  => $this->slug,
            'token' => $this->invite_token,
        ]);
    }

    /**
     * Рабочие часы мастера с дефолтом (для обратной совместимости).
     * Если work_hours = null (у старых мастеров) — 6:00–22:00 все дни без обеда.
     */
    public function effectiveWorkHours(): array
    {
        if (!empty($this->work_hours)) {
            return $this->work_hours;
        }

        $default = [];
        for ($i = 1; $i <= 7; $i++) {
            $default[(string) $i] = [
                'start' => '06:00',
                'end' => '22:00',
                'break_start' => null,
                'break_end' => null,
            ];
        }
        return $default;
    }

    /**
     * Конфиг работы на конкретный день недели.
     * Возвращает null, если день нерабочий.
     */
    public function workHoursForDay(int $isoDay): ?array
    {
        $hours = $this->effectiveWorkHours();
        $day = $hours[(string) $isoDay] ?? null;

        if (empty($day) || empty($day['start']) || empty($day['end'])) {
            return null;
        }

        return $day;
    }

    /**
     * Удобный список дней недели для UI.
     */
    public static function weekDayLabels(): array
    {
        return [
            1 => 'Понедельник',
            2 => 'Вторник',
            3 => 'Среда',
            4 => 'Четверг',
            5 => 'Пятница',
            6 => 'Суббота',
            7 => 'Воскресенье',
        ];
    }

    public static function allowedSlotSteps(): array
    {
        return [5, 10, 15, 20, 30, 60];
    }

    public function effectiveSlotStep(): int
    {
        $step = (int) ($this->slot_step ?: 5);
        return in_array($step, self::allowedSlotSteps(), true) ? $step : 5;
    }
}
