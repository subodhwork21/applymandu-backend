<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduleApplicationInterview extends Model
{
    protected $table = 'schedule_application_interviews';
    protected $fillable = [
        'application_id',
        'interview_type_id',
        'date',
        'time',
        'mode',
        'interviewer_id',
        'notes',
        'status',
    ];

    public function interviewType()
    {
        return $this->belongsTo(ApplicationInterviewType::class, 'interview_type_id');
    }

    public function interviewer()
    {
        return $this->belongsTo(ApplicationInterviewers::class, 'interviewer_id');
    }
}
