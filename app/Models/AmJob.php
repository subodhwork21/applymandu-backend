<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class AmJob extends Model
{
    use Searchable;
    use SoftDeletes;
    use HasFactory;
    protected $table = 'am_jobs';
    protected $fillable = [
        'employer_id',
        'title',
        'experience_level',
        'location',
        'company_name',
        'description',
        'employment_type',
        'salary_min',
        'salary_max',
        'requirements',
        'responsibilities',
        'benefits',
        'posted_date',
        'employer_id',
        'status',
        'department',
        'application_deadline',
        'location_type',
        'slug',
    ];

    public function user(){
        return $this->belongsToMany(User::class, 'applications', 'job_id', 'user_id')
            ->using(Application::class)
            ->withPivot('year_of_experience', 'expected_salary', 'notice_period', 'cover_letter')
            ->withTimestamps();
    }

    public function applications(){
        return $this->hasMany(Application::class, 'job_id');    
    }

    public function skills(){
        return $this->belongsToMany(Skill::class, 'job_skills', 'job_id', 'skill_id');
    }

    public function employer(){
        return $this->belongsTo(User::class, 'employer_id');
    }

}
