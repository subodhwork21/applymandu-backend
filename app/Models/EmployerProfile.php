<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployerProfile extends Model
{
    protected $table = 'employer_profiles';
    protected $fillable = [
        'user_id',
        'address',
        'website',
        'description',
        'logo',
        'industry',
        'size',
        'founded_year',
        'two_fa',
        'notification',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function jobs()
    {
        return $this->hasMany(AmJob::class, 'company_id');
    }
}
