<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SavedCandidate extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'employer_id',
        'jobseeker_id',
        'notes',
        'saved_at'
    ];
    
    protected $casts = [
        'saved_at' => 'datetime',
    ];
    
    /**
     * Get the employer who saved this candidate
     */
    public function employer()
    {
        return $this->belongsTo(User::class, 'employer_id');
    }
    
    /**
     * Get the jobseeker who was saved
     */
    public function jobseeker()
    {
        return $this->belongsTo(User::class, 'jobseeker_id');
    }

    public function amJob()
    {
        return $this->belongsTo(AmJob::class, 'job_id');
    }
}
