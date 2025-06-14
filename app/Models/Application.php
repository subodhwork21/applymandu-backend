<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;

class Application extends Pivot
{
    protected $table = 'applications';

    protected $fillable = [
        'job_id',
        'user_id',
        'year_of_experience',
        'expected_salary',
        'notice_period',
        'cover_letter',
        'applied_at',
        'updated_at',
        'status',
    ];

    public function applicationStatusHistory()
    {
        return $this->hasMany(ApplicationStatusHistory::class, 'application_id');
    }

    // Application.php
    public function job()
    {
        return $this->belongsTo(AmJob::class, 'job_id');
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
