<?php

namespace App\Http\Requests\ChatBox;

use App\Rules\Phone;
use Illuminate\Foundation\Http\FormRequest;

class SentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->can('chat_box');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'sender_id' => ['required', new Phone($this->sender_id)],
            'recipient' => 'required',
            'message'   => 'required',
        ];
    }

    /**
     * Override the `all` method to prevent trimming whitespace for specific fields.
     *
     * @param array|null $keys
     * @return array
     */
    public function all($keys = null): array
    {
        // Get all the input data without automatic trimming
        $data = parent::all($keys);

        // Ensure specific fields like 'message' are not trimmed
        $data['message'] = $this->input('message', null);

        return $data;
    }
}
