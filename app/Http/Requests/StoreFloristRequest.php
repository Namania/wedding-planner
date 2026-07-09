<?php

namespace App\Http\Requests;

use App\Models\Florist;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFloristRequest extends FormRequest
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
            'price' => 'required|numeric|min:0',
            'phone' => 'nullable|string|max:30',
            'website' => 'nullable|url|max:255',
            'style' => ['nullable', 'string', Rule::in(Florist::STYLES)],
            'note' => 'nullable|string',
            'quote_status' => ['nullable', 'string', Rule::in(Florist::QUOTE_STATUSES)],
        ];
    }
}
