<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSimulationRequest extends FormRequest
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
     * Ce endpoint sert à la fois au renommage et au changement de sélection
     * par catégorie (lieu/traiteur/fleuriste) : chaque champ est optionnel,
     * seuls ceux envoyés par le client sont mis à jour.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'venue_id' => 'sometimes|nullable|exists:venues,id',
            'caterer_id' => 'sometimes|nullable|exists:caterers,id',
            'florist_id' => 'sometimes|nullable|exists:florists,id',
        ];
    }
}
