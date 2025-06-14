<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class JobRequest extends FormRequest
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
            'title' => 'required|string|max:255',
            'location' => 'required|string|max:255',
            'description' => 'required|string',
            'location_type' => 'required|string:in:on-site,remote,hybrid',
            'experience_level' => ['required', 'string', Rule::in(['Entry Level', 'Mid Level', 'Senior Level'])],
            'employment_type' => [
                'required',
                'string',
                Rule::in(['Full-time', 'Part-time', 'Contract', 'Remote', 'Internship']),
            ],
            'salary_min' => 'required|numeric|min:0|max:99999999.99|decimal:0,2',
            'salary_max' => 'required|numeric|min:0|max:99999999.99|decimal:0,2|gte:salary_min',
            'requirements' => 'required|json',
            'responsibilities' => 'required|json',
            'benefits' => 'required|json',
            'posted_date' => 'nullable|date|before_or_equal:today',
            'employer_id' => 'nullable|exists:companies,id',
            'skills' => 'sometimes|array',
            'skills.*' => 'string',
            'department' => 'required|string|in:it,engineering,design,marketing,sales,finance,hr,operations,product,customer_support',
            'application_deadline' => 'required|date|after_or_equal:today',
        ];
    }

    protected function prepareForValidation()
    {
        // Convert string JSON fields to JSON if they're received as arrays
        $jsonFields = ['requirements', 'responsibilities', 'benefits'];

        foreach ($jsonFields as $field) {
            if ($this->has($field) && is_array($this->input($field))) {
                $this->merge([
                    $field => json_encode($this->input($field))
                ]);
            }
        }

        // Handle boolean fields
        if ($this->has('is_remote') && is_string($this->is_remote)) {
            $this->merge([
                'is_remote' => $this->is_remote === 'true' || $this->is_remote === '1'
            ]);
        }
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
