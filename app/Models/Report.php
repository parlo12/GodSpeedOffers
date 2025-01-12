<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    use HasFactory;
    protected $fillable = [
        'contact_uid',
        'call_sid',
        'contact_name',
        'call_status',
        'group_name',
        'phone',
        'campaign_id'
    ];
}
