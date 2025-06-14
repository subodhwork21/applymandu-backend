<?php

namespace App\Http\Requests;

use App\Models\ApiKey;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreApiKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && Auth::user()->whereHas('roles', function($q){
            $q->where('name', 'employer');
        })->exists();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['required', 'string', Rule::in(array_keys(ApiKey::PERMISSIONS))],
            'monthly_limit' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'API key name is required.',
            'permissions.required' => 'At least one permission must be selected.',
            'permissions.min' => 'At least one permission must be selected.',
            'permissions.*.in' => 'Invalid permission selected.',
            'monthly_limit.min' => 'Monthly limit must be at least 1.',
            'monthly_limit.max' => 'Monthly limit cannot exceed 100,000.',
            'expires_at.after' => 'Expiry date must be in the future.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['employer_id' => auth()->id()]);
    }

     public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'error' => true,
            'message' => 'Validation failed',
            'errors' => $validator->errors()
        ], 422));
    }

    
}
