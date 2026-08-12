<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateGuestRequest extends FormRequest
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
            'role' => 'nullable|string|in:witness,groomsman,bridesmaid',
            'confirmed' => 'nullable|boolean',
            'attendance' => 'nullable|array',
            'attendance.*' => 'string|in:ceremony,cocktail,dinner,brunch',
            'seating_table_id' => 'nullable|exists:seating_tables,id',
        ];
    }
}
