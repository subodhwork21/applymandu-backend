<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobSeekerReference extends Model
{
    use HasFactory;

    protected $fillable = ['name','user_id', 'position', 'company', 'email', 'phone'];

    public function userInformation()
    {
        return $this->belongsTo(User::class);
    }
}
