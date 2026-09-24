<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
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

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
