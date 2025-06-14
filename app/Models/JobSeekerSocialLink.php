<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobSeekerSocialLink extends Model
{
    use HasFactory;

    protected $fillable = ['url', 'user_id', 'platform'];

    public function userInformation()
    {
        return $this->belongsTo(User::class);
    }
}
