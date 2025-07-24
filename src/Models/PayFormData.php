<?php

namespace Ingenius\Payforms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PayFormData extends Model
{
    use HasFactory;

    protected $table = 'payforms_data';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'payform_id',
        'name',
        'icon',
        'description',
        'active',
        'args',
        'currencies',
    ];

    protected $casts = [
        'args' => 'array',
        'currencies' => 'array',
    ];
}
