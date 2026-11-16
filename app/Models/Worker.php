<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Shop workers, entered from Accounts -> Workers. */
class Worker extends Model
{
    protected $table = 'workers';

    protected $fillable = [
        'name', 'phone', 'nic', 'address', 'job_role', 'daily_wage', 'joined_date', 'is_active', 'notes',
    ];

    protected $casts = [
        'daily_wage' => 'float',
        'joined_date' => 'date:Y-m-d',
        'is_active' => 'boolean',
    ];
}
