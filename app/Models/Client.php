<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
                $client->phone_normalized = User::normalizePhone($client->phone);
            }
            if ($client->isDirty('phone2') || $client->phone2_normalized === null) {
                $client->phone2_normalized = User::normalizePhone($client->phone2);
            }
        });
    }

    // =========================
    // Relations
    // =========================

    /** Мастер, которому принадлежит карточка */
    public function master(): BelongsTo
    {
        return $this->belongsTo(User::class, 'master_id');
    }

    /** Глобальный пользователь-клиент, если он зарегистрирован */
    public function clientUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_user_id');
    }

    /** Записи этой карточки у мастера */
    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'client_id');
    }

    /** Услуги, которые клиент постоянно делает у этого мастера */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'client_service');
    }

    // =========================
    // Legacy alias
    // =========================

    /**
     * @deprecated Оставлено для совместимости со старым кодом.
     * Используйте master() или clientUser().
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
}
