<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class JobSeekerStats extends Model
{
    protected $table = 'job_seeker_stats';

    protected $fillable = [
        'user_id',
        'success_rate',
        'response_rate',
        'interview_rate',
        'created_at',
        'updated_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }


    public static function calculateStats()
    {
        $userId = Auth::user()?->id;
        if (!$userId) {
            return;
        }
        $totalApplications = Application::where('user_id', $userId)->count();
        $totalInterviews = Application::where('user_id', $userId)
            ->whereHas('applicationStatusHistory', function ($query) {
                $query->where('status', 'interview');
            })
            ->count();

        $totalResponses = Activity::whereIn('subject_id', Application::where('user_id', $userId)->pluck('id'))
            ->where('type', 'profile_viewed')
            ->count();

        if ($totalApplications > 0) {
            // Cap response rate at 100%
            $responseRate = min(($totalResponses / $totalApplications) * 100, 100);
            // Cap interview rate at 100%
            $interviewRate = min(($totalInterviews / $totalApplications) * 100, 100);
            $successRate = $responseRate * 0.5 + $interviewRate * 0.5;

            self::updateOrCreate(
                ['user_id' => $userId],
                [
                    'success_rate' => $successRate,
                    'response_rate' => $responseRate,
                    'interview_rate' => $interviewRate
                ]
            );
        }

        return  [
            'success_rate' => $successRate ?? 0,
            'response_rate' => $responseRate ?? 0,
            'interview_rate' => $interviewRate ?? 0
        ];
    }
}
