<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobAlert extends Model
{
    protected $fillable = [
        'alert_title',
        'job_category',
        'experience_level',
        'salary_min',
        'salary_max',
        'location',
        'keywords',
        'alert_frequency',
        'status',
        'user_id',
    ];
}
