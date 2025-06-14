<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $table = 'companies';
    protected $fillable = [
        'name',
        'description',
        'website_url',
        'linkedin_url',
        'employee_count_min',
        'employee_count_max',
    ];
}
