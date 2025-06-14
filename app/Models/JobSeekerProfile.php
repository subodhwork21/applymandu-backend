<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobSeekerProfile extends Model
{
    protected $table = 'job_seeker_profiles';
    protected $fillable = [
        'first_name',
        'middle_name',
        'last_name',
        'district',
        'municipality',
        'city_tole',
        'date_of_birth',
        'mobile',
        'industry',
        'preferred_job_type',
        'gender',
        'has_driving_license',
        'has_vehicle',
        'career_objectives',
        'looking_for',
        'salary_expectations',
        'user_id'
    ];

    public function userInformation()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected $casts = [
        'date_of_birth' => 'date',
        'has_driving_license' => 'boolean',
        'has_vehicle' => 'boolean',
    ];


    public function getFullNameAttribute()
    {
        return $this->middle_name
            ? "{$this->first_name} {$this->middle_name} {$this->last_name}"
            : "{$this->first_name} {$this->last_name}";
    }

 
    public function getFullAddressAttribute()
    {
        return "{$this->city_tole}, {$this->municipality}, {$this->district}";
    }
  
}
