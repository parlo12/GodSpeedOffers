<?php

namespace App\Exports;

use App\Models\Contact;
use App\Models\Workflow;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ContactsExport implements FromQuery, WithHeadings
{
    protected $id;
    protected $last_step;
    public function __construct($id,$last_step)
    {
        $this->id = $id;
        $this->last_step=$last_step;
    }

    public function query()
    {
        // return $this->contacts;
        // Start the query with the workflow_id and select only the necessary columns
       return  Contact::where('workflow_id', $this->id)
            ->where('response', 'No')
            ->where('current_step', $this->last_step) // Ensure contact is in the last step
            ->select('id', 'phone','contact_name',  'address','status', 'response', 'zipcode', 'city', 'state','email','age','gender','lead_score', 'offer','agent','created_at', 'updated_at'); // Specify the columns to include
    }

    public function headings(): array
    {
        return [
            'ID',
            'Phone',
            'Contact Name',
            'address',
            'Status',
            'Response',
            'Zipcode',
            'City',
            'State',
            'email',
            'age',
            'gender',
            'lead_score',
            'offer',
            'agent',
            'Created At',
            'Updated At'
        ];
    }
}
