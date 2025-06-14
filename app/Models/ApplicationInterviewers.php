<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationInterviewers extends Model
{
    protected $table = 'application_interviewers';
    protected $fillable = ['name', 'department', 'user_id'];
}
