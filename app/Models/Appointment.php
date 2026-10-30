<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',          // legacy — оставляем, будем синхронизировать с master_id
        'master_id',
        'client_id',
        'client_user_id',
        'service_id',
        'client_name',
        'client_phone',
        'client_email',
        'start_time',
        'end_time',
        'status',
        'notes',
        'service_ids',
        'total_price',
        'total_duration',
        'is_fixed_price',
        'custom_duration',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'service_ids' => 'array',
        'total_price' => 'decimal:2',
        'total_duration' => 'integer',
        'is_fixed_price' => 'boolean',
    ];

    protected static function booted(): void
    {
        // Синхронизация legacy user_id ↔ master_id, пока не удалили user_id
        static::saving(function (Appointment $appointment) {
            if (!$appointment->master_id && $appointment->user_id) {
                $appointment->master_id = $appointment->user_id;
            }
            if (!$appointment->user_id && $appointment->master_id) {
                $appointment->user_id = $appointment->master_id;
            }

            // Автоподстановка client_user_id, если задан client_id
            if (!$appointment->client_user_id && $appointment->client_id) {
                $client = Client::find($appointment->client_id);
                if ($client && $client->client_user_id) {
                    $appointment->client_user_id = $client->client_user_id;
                }
            }
        });
    }

    // =========================
    // Relations
    // =========================

    /** Мастер, который ведёт запись */
    public function master(): BelongsTo
    {
        return $this->belongsTo(User::class, 'master_id');
    }

    /** Глобальный пользователь-клиент (если он зарегистрирован) */
    public function clientUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_user_id');
    }

    /** Карточка клиента у мастера (локальные данные) */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    /** Услуга (основная, legacy) */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @deprecated Используйте master() или clientUser().
     * Оставлено для совместимости со старым кодом.
     */
    public function user(): BelongsTo
    {
        return $this->master();
    }

    /** Доходы именно этой записи и её мастера; ручные операции без связи не затрагиваются. */
    public function incomeTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class)
            ->where('type', 'income')
            ->where('user_id', $this->master_id ?: $this->user_id);
    }

    protected function performDeleteOnModel()
    {
        // Сначала удаляем доходы, пока FK appointment_id ещё не обнулён.
        // При ошибке любого DELETE откатываем обе операции.
        $this->getConnection()->transaction(function () {
            $this->incomeTransactions()->delete();
            parent::performDeleteOnModel();
        });
    }

    // =========================
    // Синхронизация транзакции
    // =========================

    public function syncTransaction(): void
    {
        $existing = $this->incomeTransactions()->first();

        if ($this->status === 'completed') {
            // Транзакция принадлежит мастеру (user_id в transactions)
            $masterId = $this->master_id ?: $this->user_id;
            $master = $masterId ? User::find($masterId) : null;

            if (!$master) {
                return;
            }

            if (!$existing) {
                $master->transactions()->create([
                    'type' => 'income',
                    'amount' => $this->total_price,
                    'transaction_date' => $this->start_time ?? now(),
                    'category' => 'Доход от услуги',
                    'description' => 'Запись: ' . $this->client_name,
                    'appointment_id' => $this->id,
                ]);
            } else {
                $existing->update([
                    'amount' => $this->total_price,
                    'transaction_date' => $this->start_time ?? now(),
                    'description' => 'Запись: ' . $this->client_name,
                ]);
            }
        } else {
            // Убираем также возможные дубли старого дохода при отмене/смене статуса.
            $this->incomeTransactions()->delete();
        }
    }
}
