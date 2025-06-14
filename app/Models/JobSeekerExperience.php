<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobSeekerExperience extends Model
{
    protected $fillable = [
        'user_id',
        'position_title',
        'company_name',
        'industry',
        'job_level',
        'roles_and_responsibilities',
        'start_date',
        'end_date',
        'currently_work_here',
        
    ];


    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'currently_work_here' => 'boolean',
    ];


    public function userInformation()
    {
        return $this->belongsTo(User::class);
    }

    public function getDurationAttribute()
    {
        $startDate = $this->start_date->format('M Y');
        
        if ($this->currently_work_here) {
            return "{$startDate} - Present";
        }
        
        return "{$startDate} - " . ($this->end_date ? $this->end_date->format('M Y') : 'N/A');
    }


    public function getRolesArrayAttribute()
    {
        if (empty($this->roles_and_responsibilities)) {
            return [];
        }
        
        return explode("\n", $this->roles_and_responsibilities);
    }
}
