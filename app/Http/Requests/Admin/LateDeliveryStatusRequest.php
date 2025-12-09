<?php

namespace App\Http\Requests\Admin;

use App\Traits\ResponseHandler;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class LateDeliveryStatusRequest extends FormRequest
{
	use ResponseHandler;
	public function authorize(): bool
	{
		return true;
	}

	public function rules(): array
	{
		return [
			'id' => 'required',
			'late_status' => 'required|in:pending,in_progress,resolved,rejected',
			'rejected_note' => $this->input('late_status') == 'rejected' ? 'required' : '',
			// resolved_note is optional as per requirement
		];
	}

	public function messages(): array
	{
		return [
			'rejected_note.required' => translate('The_rejected_note_field_is_required'),
		];
	}

	protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
	{
		throw new HttpResponseException(response()->json(['errors' => $this->errorProcessor($validator)]));
	}
}


