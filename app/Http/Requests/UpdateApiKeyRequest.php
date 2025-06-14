<?php

namespace App\Http\Requests;

use App\Models\ApiKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateApiKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $apiKey = $this->route('apiKey');
        return auth()->check() && 
               Auth::user()->whereHas('roles', function($q){
                $q->where('name', 'employer');
               })->exists() && 
               $apiKey && 
               $apiKey->employer_id === auth()->id();
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'permissions' => ['sometimes', 'required', 'array', 'min:1'],
            'permissions.*' => ['required', 'string', Rule::in(array_keys(ApiKey::PERMISSIONS))],
            'status' => ['sometimes', Rule::in([ApiKey::STATUS_ACTIVE, ApiKey::STATUS_INACTIVE])],
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
            'status.in' => 'Invalid status selected.',
            'monthly_limit.min' => 'Monthly limit must be at least 1.',
            'monthly_limit.max' => 'Monthly limit cannot exceed 100,000.',
            'expires_at.after' => 'Expiry date must be in the future.',
        ];
    }
}
