<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TutoringAvailability extends Model
{
    use HasFactory;

    protected $table = 'availabilities';

    protected $fillable = [
        'user_id',
        'day_of_week',
        'start_time',
        'end_time'
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime:H:i',
            'end_time' => 'datetime:H:i',
        ];
    }

    // Relación con el profesor (User)
    public function teacher()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
