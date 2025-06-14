<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationStatusHistory extends Model
{
    protected $table = 'application_status_histories';

    protected $fillable = [
        'application_id',
        'status',
        'remarks',
        'changed_at',
        'updated_at',
        'created_at',
    ];

    public function job()
    {
        return $this->belongsTo(AmJob::class, 'job_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
