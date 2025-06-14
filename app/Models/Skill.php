<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Skill extends Model
{
    use HasFactory;
    protected $table = 'skills';
    protected $fillable = [
        'name',
    ];


    public function jobs(){
        return $this->belongsToMany(AmJob::class, 'job_skills', 'skill_id', 'job_id');
    }

    public function jobseekers()
    {
        return $this->belongsToMany(User::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'skill_job_seekers', 'skill_id', 'user_id');
    }
}
