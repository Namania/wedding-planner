<?php

namespace App\Http\Requests;

use App\Models\Venue;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVenueRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'website' => 'nullable|url|max:255',
            'maps_url' => 'nullable|url|max:2048',
            'price' => 'required|numeric|min:0',
            'note' => 'nullable|string',
            'quote_status' => ['nullable', 'string', Rule::in(Venue::QUOTE_STATUSES)],
        ];
    }
}
