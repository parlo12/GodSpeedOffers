<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CancelledContracts extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'contact_id',
        'organisation_id',
        'user_id'

    ];
}