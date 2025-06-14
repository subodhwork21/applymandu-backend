<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationInterviewType extends Model
{
    protected $table = 'application_interview_types';
    protected $fillable = ['name', 'user_id'];
}
