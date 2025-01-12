<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Assistant extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'prompt',
        'file1',
        'file2',
        'file1_id',
        'file2_id',
        'openAI_id',
        'sleep_time',
        'sleep_time_units',
        'min_wait_time',
        'max_wait_time',
        'wait_time_units',
        'maximum_messages',
        'openAI',
        'organisation_id'
    ];
}
