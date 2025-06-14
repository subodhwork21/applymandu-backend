<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobSeekerCertificate extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'year', 'user_id', 'issuer'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
