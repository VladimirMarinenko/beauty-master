<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'phone',
        'contact',
        'is_permanent',
    ];

    protected $casts = [
        'is_permanent' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (Client $client) {
            $client->phone_normalized = self::normalizePhone($client->phone);
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function services()
    {
        return $this->belongsToMany(Service::class);
    }

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
     * Возвращает варианты цифровой строки для поиска по телефону:
     * если начинается с 8 — добавляет вариант с 7, и наоборот.
     * Так «8666» находит «7666…», а «7961» — «8961…».
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
