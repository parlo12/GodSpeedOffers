<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Workflow extends Model
{
    use HasFactory;
    protected $fillable = [
        'steps',
        'steps_flow',
        'name',
        'contact_group',
        'active',
        'group_id',
        'voice',
        'agent_number',
        'texting_number',
        'calling_number',
        'folder_id',
        'godspeedoffers_api',
        'organisation_id',
        'user_id'

    ];
    public function contacts()
    {
        return $this->hasMany(Contact::class, 'workflow_id');
    }
}
