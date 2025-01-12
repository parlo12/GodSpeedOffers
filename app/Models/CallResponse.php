<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CallResponse extends Model
{
    protected $fillable = ['caller_id', 'question_number', 'answer'];
}
