<?php

namespace App\Http\Requests\Admin;

use App\Traits\ResponseHandler;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CityZoneShippingUpdateRequest extends FormRequest
{
    use ResponseHandler;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $cityZoneShippingId = $this->route('cityZoneShipping')->id;
        
        return [
            'governorate_id' => [
                'required',
                'exists:governorates,id',
                Rule::unique('city_shipping_costs', 'governorate_id')->ignore($cityZoneShippingId)
            ],
            'cost' => 'required|numeric|min:0|max:999999.99',
        ];
    }

    public function messages(): array
    {
        return [
            'governorate_id.required' => translate('governorate_is_required'),
            'governorate_id.exists' => translate('selected_governorate_is_invalid'),
            'governorate_id.unique' => translate('shipping_cost_for_this_governorate_already_exists'),
            'cost.required' => translate('shipping_cost_is_required'),
            'cost.numeric' => translate('shipping_cost_must_be_a_number'),
            'cost.min' => translate('shipping_cost_must_be_at_least_0'),
            'cost.max' => translate('shipping_cost_cannot_exceed_999999.99'),
        ];
    }

    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        throw new HttpResponseException(response()->json(['errors' => $this->errorProcessor($validator)]));
    }
}
