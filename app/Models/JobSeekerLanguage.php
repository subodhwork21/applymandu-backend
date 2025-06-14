<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobSeekerLanguage extends Model
{
    protected $fillable = ['language', 'proficiency', 'user_id'];

    public function userInformation()
    {
        return $this->belongsTo(User::class);
    }
}
