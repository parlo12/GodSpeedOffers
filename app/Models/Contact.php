<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    use HasFactory;
    protected $fillable = [
        'uuid',
        'current_step',
        'next_step',
        'workflow_id',
        'contact_communication_ids',
        'phone',
        'can_send',
        'can_send_after',
        'response',
        'contact_name',
        'status',
        'valid_lead',
        'offer_made',
        'contract_executed',
        'contract_cancelled',
        'deal_closed',
        'cost',
        'subscribed',
        'organisation_id',
        'user_id',
        'zipcode',
        'city',
        'state',
        'address',
        'offer',
        'email',
        'age',
        'gender',
        'lead_score',
        'agent'
    ];
    public function workflow()
    {
        return $this->belongsTo(Workflow::class, 'workflow_id');
    }
}
