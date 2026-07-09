<?php

namespace App\Http\Requests;

use App\Models\Outfit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOutfitRequest extends FormRequest
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
            'spouse' => ['required', 'string', Rule::in(Outfit::SPOUSES)],
            'phone' => 'nullable|string|max:30',
            'website' => 'nullable|url|max:255',
            'image' => 'nullable|image|max:5120',
            'remove_image' => 'sometimes|boolean',
            'note' => 'nullable|string',
            'quote_status' => ['nullable', 'string', Rule::in(Outfit::QUOTE_STATUSES)],
        ];
    }
}
