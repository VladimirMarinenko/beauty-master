<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'master_id',
        'client_user_id',
        'name',
        'phone',
        'phone2',
        'contact',
        'is_permanent',
    ];

    protected $casts = [
        'is_permanent' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (Client $client) {
            if ($client->isDirty('phone') || $client->phone_normalized === null) {
                $client->phone_normalized = self::normalizePhone($client->phone);
            }
            if ($client->isDirty('phone2') || $client->phone2_normalized === null) {
                $client->phone2_normalized = self::normalizePhone($client->phone2);
            }

            // Синхронизация legacy user_id ↔ master_id, пока не удалили user_id
            if (!$client->master_id && $client->user_id) {
                $client->master_id = $client->user_id;
            }
            if (!$client->user_id && $client->master_id) {
                $client->user_id = $client->master_id;
            }
        });
    }

    // =========================
    // Relations
    // =========================

    public function master(): BelongsTo
    {
        return $this->belongsTo(User::class, 'master_id');
    }

    public function clientUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_user_id');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'client_id');
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'client_service');
    }

    /**
     * @deprecated Используйте master() или clientUser().
     */
    public function user(): BelongsTo
    {
        return $this->master();
    }

    // =========================
    // Scopes
    // =========================

    public function scopeForMaster($query, int $masterId)
    {
        return $query->where('master_id', $masterId);
    }

    // =========================
    // Helpers (используются в ClientController)
    // =========================

    /**
     * Нормализует телефон: только цифры, 8→7 для 11-значных.
     */
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

    /**
     * Грубая эвристика: похоже ли введённое на телефон.
     */
    public static function looksLikePhone(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        if (preg_match('/\p{L}/u', $value)) {
            return false;
        }

        return strlen(preg_replace('/\D+/u', '', $value)) >= 3;
    }

    /**
     * Варианты цифровой строки для поиска: 8↔7 в начале.
     */
    public static function phoneSearchVariants(string $digits): array
    {
        if ($digits === '') {
            return [];
        }

        $variants = [$digits];

        if (strlen($digits) >= 2) {
            if ($digits[0] === '8') {
                $variants[] = '7' . substr($digits, 1);
            } elseif ($digits[0] === '7') {
                $variants[] = '8' . substr($digits, 1);
            }
        }

        return array_values(array_unique($variants));
    }
}
