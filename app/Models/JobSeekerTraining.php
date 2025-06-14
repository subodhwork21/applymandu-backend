<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobSeekerTraining extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'description', 'user_id', 'institution'];

    public function userInformation()
    {
        return $this->belongsTo(User::class);
    }
}
