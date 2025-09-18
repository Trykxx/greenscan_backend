<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'exposant_id',
        'company_name',
        'siren_number',
    ];

    public function exposant()
    {
        return $this->belongsTo(Exposant::class);
    }
}
