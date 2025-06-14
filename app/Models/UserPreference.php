<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPreference extends Model
{
    protected $fillable = [
        'user_id',
        'visible_to_employers',
        'appear_in_search_results',
        'show_contact_info',
        'show_online_status',
        'allow_personalized_recommendations',
        'email_job_matches',
        'sms_application_updates',
        'subscribe_to_newsletter',
        'immediate_availability',
        'availability_date',
    ];

    protected $casts = [
        'visible_to_employers' => 'boolean',
        'appear_in_search_results' => 'boolean',
        'show_contact_info' => 'boolean',
        'show_online_status' => 'boolean',
        'allow_personalized_recommendations' => 'boolean',
        'email_job_matches' => 'boolean',
        'sms_application_updates' => 'boolean',
        'subscribe_to_newsletter' => 'boolean',
        'immediate_availability' => 'boolean',
        'availability_date' => 'date',
    ];
}
