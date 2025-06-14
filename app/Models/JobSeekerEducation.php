<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobSeekerEducation extends Model
{

    protected $table = 'job_seeker_education';


    protected $fillable = [
        'user_id',
        'degree',
        'subject_major',
        'institution',
        'university_board',
        'grading_type',
        'joined_year',
        'passed_year',
        'currently_studying',
    ];


    protected $casts = [
        'joined_year' => 'date',
        'passed_year' => 'date',
        'currently_studying' => 'boolean',
    ];


    public function userInformation()
    {
        return $this->belongsTo(User::class);
    }


    public function getDurationAttribute()
    {
        $startYear = $this->joined_year->format('Y');
        
        if ($this->currently_studying) {
            return "{$startYear} - Present";
        }
        
        return "{$startYear} - " . ($this->passed_year ? $this->passed_year->format('Y') : 'N/A');
    }

 
    public function getIsCompletedAttribute()
    {
        return !$this->currently_studying && !is_null($this->passed_year);
    }
}
