<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DemoRequest extends Model
{
    protected $fillable = [
        'full_name',
        'organization',
        'inn',
        'kpp',
        'email',
        'phone',
        'items_list',
        'terms_accepted',
        'status',
        'notes',
        'password_setup_token',
        'password_setup_token_expires_at',
    ];

    protected $casts = [
        'terms_accepted' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'password_setup_token_expires_at' => 'datetime',
    ];
}
