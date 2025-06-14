<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class EmployerRegisterRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'company_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|digits|max:255',
            // 'address' => 'required|string|max:255',
            // 'website' => 'nullable|url|max:255',
            // 'logo' => 'required|string|max:255',
            // 'description' => 'required|string',
            // 'industry' => 'required|string|max:255',
            // 'size' => 'required|string|max:255',
            // 'founded_year' => 'required|string|max:4', // you could validate more strictly if needed
            // 'two_fa' => 'nullable|boolean',
            'status' => 'nullable|boolean',
            // 'notification' => 'nullable|boolean',
            'email_verified_at' => 'nullable|date',
            'reset_password_token' => 'nullable|string|max:255',
            'verify_email_token' => 'nullable|string|max:255',
            'password' => 'required|string|min:8', // password rules can be stricter
            'remember_token' => 'nullable|string|max:100',
        ];
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
