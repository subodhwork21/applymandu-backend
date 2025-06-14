<?php

namespace App\Models;

use App\Models\Scopes\ActiveUsersScope;
use Dom\Attr;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;


#[ScopedBy([ActiveUsersScope::class])]


class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, HasRoles, HasApiTokens;


    protected $fillable = [
        'id',
        'first_name',
        'last_name',
        'company_name',
        'image',
        'email',
        'password',
        'phone',
        'verify_email_token',
        'secret_key',
    ];


    protected $hidden = [
        'password',
        'remember_token',
    ];


    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function employerProfile()
    {
        return $this->hasOne(EmployerProfile::class, 'user_id');
    }


    public function jobs()
    {
        return $this->belongsToMany(AmJob::class, 'applications', 'user_id', 'job_id')
            ->using(Application::class)
            ->withPivot('year_of_experience', 'expected_salary', 'notice_period', 'cover_letter')
            ->withTimestamps();
    }
    public function applications()
    {
        return $this->hasMany(Application::class, 'user_id');
    }



    public function selectedCompany()
    {
        return $this->belongsTo(Company::class, "company_id");
    }


    public function hasRole($role)
    {
        return $this->roles()->where('name', $role)->exists();
    }
    public function hasAnyRole($roles)
    {
        return $this->roles()->whereIn('name', $roles)->exists();
    }
    public function hasPermission($permission)
    {
        return $this->hasAnyRole($permission->roles);
    }

    public function jobSeekerProfile()
    {
        return $this->hasOne(JobSeekerProfile::class, 'user_id');
    }

    public function experiences()
    {
        return $this->hasMany(JobSeekerExperience::class);
    }

    public function educations()
    {
        return $this->hasMany(JobSeekerEducation::class);
    }


    public function skills()
    {
        return $this->belongsToMany(Skill::class, 'skill_job_seekers', 'user_id', 'skill_id');
    }

    public function languages()
    {
        return $this->hasMany(JobSeekerLanguage::class);
    }

    public function trainings()
    {
        return $this->hasMany(JobSeekerTraining::class);
    }

    public function certificates()
    {
        return $this->hasMany(JobSeekerCertificate::class);
    }

    public function socialLinks()
    {
        return $this->hasMany(JobSeekerSocialLink::class);
    }

    public function references()
    {
        return $this->hasMany(JobSeekerReference::class);
    }


    public function jobSeekerStats()
    {
        return $this->hasOne(JobSeekerStats::class, 'user_id');
    }

    public function calculateProfileCompletion(User $user, bool $forceRefresh = false)
    {
        // Define cache key based on user ID and last profile update
        $cacheKey = "profile_completion_{$user->id}_{$user->updated_at->timestamp}";
        $cacheDuration = now()->addHours(24); // Cache for 24 hours

        // If force refresh is requested or cache doesn't exist, calculate and store
        if ($forceRefresh || !Cache::has($cacheKey)) {
            $completionStatus = $this->performCalculation($user);

            // Store the calculation in cache
            Cache::put($cacheKey, $completionStatus, $cacheDuration);

            return $completionStatus;
        }

        // Return cached result
        return Cache::get($cacheKey);
    }

    private function performCalculation(User $user)
    {
        // Define section weights (customize these according to importance)
        $sectionWeights = [
            'personal_info' => 25,
            'work_experience' => 20,
            'education' => 20,
            'skills' => 10,
            'languages' => 10,
            'trainings' => 5,
            'certificates' => 5,
            'social_links' => 2.5,
            'references' => 2.5
        ];

        $completionStatus = [
            'overall_percentage' => 0,
            'sections' => []
        ];

        // Check personal information
        $personalFields = [
            'first_name',
            'last_name',
            'district',
            'municipality',
            'city_tole',
            'date_of_birth',
            'industry',
            'preferred_job_type',
            'gender'
        ];

        // The rest of the calculation code remains the same...
        // [Original calculation code from the previous response]

        $filledPersonalFields = 0;
        foreach ($personalFields as $field) {
            if (!empty($user->jobSeekerProfile->$field)) {
                $filledPersonalFields++;
            }
        }

        $personalInfoPercentage = ($filledPersonalFields / count($personalFields)) * 100;
        $completionStatus['sections']['personal_info'] = [
            'percentage' => round($personalInfoPercentage, 1),
            'completed' => $filledPersonalFields,
            'total' => count($personalFields)
        ];

        // Check work experience
        $workExpFields = ['position_title', 'company_name', 'industry', 'job_level', 'start_date'];
        $workExpPercentage = 0;

        if ($user->experiences && count($user->experiences) > 0) {
            $totalExpFields = count($workExpFields) * count($user->experiences);
            $filledExpFields = 0;

            foreach ($user->experiences as $experience) {
                foreach ($workExpFields as $field) {
                    if (!empty($experience->$field)) {
                        $filledExpFields++;
                    }
                }
            }

            $workExpPercentage = ($filledExpFields / $totalExpFields) * 100;
        }

        $completionStatus['sections']['work_experience'] = [
            'percentage' => round($workExpPercentage, 1),
            'has_entries' => $user->experiences && count($user->experiences) > 0
        ];

        // Check education
        $educationFields = ['degree', 'subject_major', 'institution', 'university_board', 'joined_year'];
        $educationPercentage = 0;

        if ($user->educations && count($user->educations) > 0) {
            $totalEduFields = count($educationFields) * count($user->educations);
            $filledEduFields = 0;

            foreach ($user->educations as $education) {
                foreach ($educationFields as $field) {
                    if (!empty($education->$field)) {
                        $filledEduFields++;
                    }
                }
            }

            $educationPercentage = ($filledEduFields / $totalEduFields) * 100;
        }

        $completionStatus['sections']['education'] = [
            'percentage' => round($educationPercentage, 1),
            'has_entries' => $user->educations && count($user->educations) > 0
        ];

        // Check skills
        $skillsPercentage = ($user->skills && count($user->skills) > 0) ? 100 : 0;
        $completionStatus['sections']['skills'] = [
            'percentage' => $skillsPercentage,
            'has_entries' => $user->skills && count($user->skills) > 0
        ];

        // Check languages
        $languagesPercentage = ($user->languages && count($user->languages) > 0) ? 100 : 0;
        $completionStatus['sections']['languages'] = [
            'percentage' => $languagesPercentage,
            'has_entries' => $user->languages && count($user->languages) > 0
        ];

        // Check trainings
        $trainingsPercentage = ($user->trainings && count($user->trainings) > 0) ? 100 : 0;
        $completionStatus['sections']['trainings'] = [
            'percentage' => $trainingsPercentage,
            'has_entries' => $user->trainings && count($user->trainings) > 0
        ];

        // Check certificates
        $certificatesPercentage = ($user->certificates && count($user->certificates) > 0) ? 100 : 0;
        $completionStatus['sections']['certificates'] = [
            'percentage' => $certificatesPercentage,
            'has_entries' => $user->certificates && count($user->certificates) > 0
        ];

        // Check social links
        $socialLinksPercentage = ($user->socialLinks && count($user->socialLinks) > 0) ? 100 : 0;
        $completionStatus['sections']['social_links'] = [
            'percentage' => $socialLinksPercentage,
            'has_entries' => $user->socialLinks && count($user->socialLinks) > 0
        ];

        // Check references
        $referencesPercentage = ($user->references && count($user->references) > 0) ? 100 : 0;
        $completionStatus['sections']['references'] = [
            'percentage' => $referencesPercentage,
            'has_entries' => $user->references && count($user->references) > 0
        ];

        // [Rest of calculation code]

        // Calculate overall percentage
        $overallPercentage =
            ($completionStatus['sections']['personal_info']['percentage'] * $sectionWeights['personal_info'] / 100) +
            ($completionStatus['sections']['work_experience']['percentage'] * $sectionWeights['work_experience'] / 100) +
            ($completionStatus['sections']['education']['percentage'] * $sectionWeights['education'] / 100) +
            ($completionStatus['sections']['skills']['percentage'] * $sectionWeights['skills'] / 100) +
            ($completionStatus['sections']['languages']['percentage'] * $sectionWeights['languages'] / 100) +
            ($completionStatus['sections']['trainings']['percentage'] * $sectionWeights['trainings'] / 100) +
            ($completionStatus['sections']['certificates']['percentage'] * $sectionWeights['certificates'] / 100) +
            ($completionStatus['sections']['social_links']['percentage'] * $sectionWeights['social_links'] / 100) +
            ($completionStatus['sections']['references']['percentage'] * $sectionWeights['references'] / 100);

        $completionStatus['overall_percentage'] = round($overallPercentage, 1);
        $completionStatus['section_weights'] = $sectionWeights;

        return $completionStatus;
    }

    public function clearCache(User $user)
    {
        $cacheKey = "profile_completion_{$user->id}_{$user->updated_at->timestamp}";
        Cache::forget($cacheKey);
    }



    public function imagePath(): Attribute
    {
        if ($this?->image) {
            $path =  Storage::disk('public')->path($this->image);
        }

        if (isset($path) && file_exists($path)) {
            return Attribute::make(
                get: fn() => asset('storage/' . $this->image),
            );
        } else {
            return Attribute::make(
                get: fn() => asset('person.png')
            );
        }
    }


    public function employerLogo(): Attribute
    {
        if ($this?->employerProfile?->logo) {
            $path =  Storage::disk('public')->path($this->employerProfile->logo);
        }

        if (isset($path) && file_exists($path)) {
            return Attribute::make(
                get: fn() => asset('storage/' . $this->employerProfile->logo),
            );
        } else {
            return Attribute::make(
                get: fn() => asset('image.png')
            );
        }
    }

    public function preferences()
    {
        return $this->hasOne(UserPreference::class);
    }

    public function jobAlerts(){
        return $this->hasMany(JobAlert::class);
    }

    public function employerJobs()
    {
        return $this->hasMany(AmJob::class, 'employer_id');
    }

   
}
