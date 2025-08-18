<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CustomerRequest extends FormRequest
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
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'f_name' => 'required',
            'phone' => 'required|unique:users|min:4|max:20',
            'city_id' => 'required|exists:governorates,id',
            'seller_id' => 'required|exists:sellers,id',
        ];
    }
    public function messages(): array
    {
        return [
            'name.required' => translate('first_name_is_required'),
            'phone.required' => translate('phone_is_required'),
            'phone.max' => translate('please_ensure_your_phone_number_is_valid_and_does_not_exceed_20_characters'),
            'phone.min' => translate('phone_number_with_a_minimum_length_requirement_of_4_characters'),
            'city_id.required' => translate('city_is_required'),
            'seller_id.required' => translate('seller_is_required'),
        ];
    }
}
