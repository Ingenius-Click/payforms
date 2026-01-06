<?php

namespace Ingenius\Payforms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Ingenius\Payforms\Services\PayformsManager;

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
        'description',
        'active',
        'args',
        'currencies',
    ];

    protected $casts = [
        'args' => 'array',
        'currencies' => 'array',
    ];

    protected $appends = [];

    public function getRulesAttribute(): array
    {
        $payFormManager = app(PayformsManager::class);
        $payform = $payFormManager->getPayform($this->payform_id, true);

        return $payform->rulesWithLabels();
    }
}
