<?php

namespace App\Http\Requests;

use App\Enums\DegreeLevel;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class ResumeRequest extends FormRequest
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
            // Personal Information
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'district' => 'required|string|max:255',
            'municipality' => 'required|string|max:255',
            'city_tole' => 'required|string|max:255',
            'date_of_birth' => 'required|date|before:today',
            'mobile' => 'nullable|string|max:20',
            'industry' => 'required|string|max:255',
            'preferred_job_type' => 'required|string|max:255',
            'gender' => 'required|in:Male,Female,Other',
            'has_driving_license' => 'boolean',
            'has_vehicle' => 'boolean',
            'career_objectives' => 'nullable|string|max:1000',
            'looking_for' => 'nullable|string|max:1000',
            'salary_expectations' => 'nullable|string|max:1000',

            // Work Experience - Array validation
            'work_experiences' => 'nullable|array',
            'work_experiences.*.position_title' => 'required|string|max:255',
            'work_experiences.*.company_name' => 'required|string|max:255',
            'work_experiences.*.industry' => 'required|string|max:255',
            'work_experiences.*.job_level' => 'required|string|max:255',
            'work_experiences.*.roles_and_responsibilities' => 'nullable|string|max:2000',
            'work_experiences.*.start_date' => 'required|date|before_or_equal:today',
            'work_experiences.*.end_date' => 'nullable|date|after_or_equal:work_experiences.*.start_date|before_or_equal:today',
            'work_experiences.*.currently_work_here' => 'boolean',

            // Education - Array validation
            'educations' => 'nullable|array',
            'educations.*.degree' => ['required', Rule::in(
                'Bachelors Degree',
                'Masters Degree',
                'PHD',
                'Diploma',
                'High School'
            )],
            'educations.*.subject_major' => 'required|string|max:255',
            'educations.*.institution' => 'required|string|max:255',
            'educations.*.university_board' => 'required|string|max:255',
            'educations.*.grading_type' => 'nullable|string|max:255',
            'educations.*.joined_year' => 'required|date',
            'educations.*.passed_year' => 'nullable|date|after_or_equal:educations.*.joined_year',
            'educations.*.currently_studying' => 'boolean',


            // Skills
            'skills' => 'sometimes|array',
            'skills.*' => 'string|max:255',

            // Languages
            'languages' => 'sometimes|array',
            'languages.*.language' => 'required_with:languages|string|max:255',
            'languages.*.proficiency' => 'required_with:languages.*.language|string|in:Native,Fluent,Intermediate,Basic',

            // Training & Courses
            'trainings' => 'sometimes|array',
            'trainings.*.title' => 'required_with:trainings|string|max:255',
            'trainings.*.description' => 'sometimes|nullable|string',
            'trainings.*.institution' => 'sometimes|nullable|string|max:255',

            // Certificates
            'certificates' => 'sometimes|array',
            'certificates.*.title' => 'required_with:certificates|string|max:255',
            'certificates.*.year' => 'sometimes|nullable|string|max:4|digits:4',
            'certificates.*.issuer' => 'sometimes|nullable|string|max:255',

            // Social Links
            'social_links' => 'sometimes|array',
            'social_links.*.url' => 'required_with:social_links|url|max:255',
            'social_links.*.platform' => 'required_with:social_links|string|max:255',

            // References
            'references' => 'sometimes|array',
            'references.*.name' => 'required_with:references|string|max:255',
            'references.*.position' => 'sometimes|nullable|string|max:255',
            'references.*.company' => 'sometimes|nullable|string|max:255',
            'references.*.email' => 'sometimes|nullable|email|max:255',
            'references.*.phone' => 'sometimes|nullable|string|max:20',
        ];
    }

    public function messages(): array
    {
        return [
            // Personal Information
            'first_name.required' => 'First name is required.',
            'first_name.string' => 'First name must be a string.',
            'first_name.max' => 'First name must not exceed 255 characters.',

            'middle_name.string' => 'Middle name must be a string.',
            'middle_name.max' => 'Middle name must not exceed 255 characters.',

            'last_name.required' => 'Last name is required.',
            'last_name.string' => 'Last name must be a string.',
            'last_name.max' => 'Last name must not exceed 255 characters.',

            'district.required' => 'District is required.',
            'municipality.required' => 'Municipality is required.',
            'city_tole.required' => 'City/Tole is required.',
            'date_of_birth.required' => 'Date of birth is required.',
            'date_of_birth.before' => 'Date of birth must be a date before today.',
            'gender.required' => 'Gender is required.',
            'gender.in' => 'Gender must be Male, Female, or Other.',

            // Work Experience
            'work_experiences.*.position_title.required' => 'Position title is required for each work experience.',
            'work_experiences.*.company_name.required' => 'Company name is required for each work experience.',
            'work_experiences.*.start_date.required' => 'Start date is required for each work experience.',
            'work_experiences.*.start_date.date' => 'Start date must be a valid date.',
            'work_experiences.*.end_date.after_or_equal' => 'End date must be after or equal to the start date.',
            'work_experiences.*.end_date.before_or_equal' => 'End date must not be in the future.',

            // Education
            'educations.*.degree.required' => 'Degree is required for each education entry.',
            'educations.*.joined_year.required' => 'Joined year is required for each education entry.',
            'educations.*.passed_year.after_or_equal' => 'Passed year must be after or equal to joined year.',

            // Languages
            'languages.*.language.required_with' => 'Language name is required.',
            'languages.*.proficiency.required_with' => 'Proficiency is required when a language is specified.',
            'languages.*.proficiency.in' => 'Proficiency must be one of: Native, Fluent, Intermediate, Basic.',

            // Trainings
            'trainings.*.title.required_with' => 'Training title is required when a training is listed.',

            // Certificates
            'certificates.*.title.required_with' => 'Certificate title is required when a certificate is listed.',
            'certificates.*.year.digits' => 'Certificate year must be a 4-digit year.',

            // Social Links
            'social_links.*.url.required_with' => 'Social link URL is required.',
            'social_links.*.url.url' => 'Each social link must be a valid URL.',

            // References
            'references.*.name.required_with' => 'Reference name is required when references are provided.',

            // General
            'skills.*.string' => 'Each skill must be a string.',
            'skills.*.max' => 'Each skill must not exceed 255 characters.',
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
