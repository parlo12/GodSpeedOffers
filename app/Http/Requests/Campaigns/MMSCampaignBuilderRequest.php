<?php

namespace App\Http\Requests\Campaigns;

use Illuminate\Foundation\Http\FormRequest;

class MMSCampaignBuilderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('mms_campaign_builder');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => 'required',
            'contact_groups' => 'required',
            'mms_file' => 'required|mimes:mp4,mov,ogg,qt,jpeg,png,jpg,gif,bmp,webp|max:20000',
            'schedule_date' => 'required_if:schedule,true|date|nullable',
            'schedule_time' => 'required_if:schedule,true|date_format:H:i',
            'timezone' => 'required_if:schedule,true|timezone',
            'frequency_cycle' => 'required_if:schedule,true',
            'frequency_amount' => 'required_if:frequency_cycle,custom|nullable|numeric',
            'frequency_unit' => 'required_if:frequency_cycle,custom|nullable|string',
            'recurring_date' => 'sometimes|date|nullable',
            'recurring_time' => 'sometimes|date_format:H:i',
        ];
    }

    /**
     * custom message
     *
     * @return string[]
     */
    public function messages(): array
    {
        return [
            'recipients.required_if' => __('locale.campaigns.contact_groups_required'),
        ];
    }
}
