<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Activity extends Model
{
    protected $table = 'activities';

    protected $fillable = [
        'user_id',
        'activity_type',
        'subject_type',
        'type',
        'subject_id',
        'description',
        'created_at',
        'updated_at',

    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function subject()
    {
        return $this->morphTo();
    }

    public static function recentJobSeekerActivity(){
        if (!Auth::check()) {
            return self::query()->whereNull('id'); // Return empty collection
        }
        
        return self::where("user_id", Auth::user()->id)
                   ->orderBy("created_at", "desc")
                   ->limit(10)->get();
    }
}
