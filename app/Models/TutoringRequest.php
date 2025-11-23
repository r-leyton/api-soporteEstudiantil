<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TutoringRequest extends Model
{
    use HasFactory;

    protected $table = 'appointments';

    protected $fillable = [
        'student_id',
        'teacher_id',
        'start_time',
        'end_time',
        'status',
        'meet_url'
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
        ];
    }

    protected $appends = ['requested_date', 'requested_time', 'subject', 'topic', 'notes', 'studentName', 'studentId'];

    // Atributos virtuales para compatibilidad con el frontend
    public function getRequestedDateAttribute()
    {
        // Retornar timestamp ISO estándar: YYYY-MM-DDTHH:MM:SS.000Z
        return $this->start_time ? $this->start_time->format('Y-m-d\TH:i:s.000\Z') : null;
    }

    public function getRequestedTimeAttribute()
    {
        return $this->start_time ? $this->start_time->format('H:i') : null;
    }

    // Campos virtuales que no existen en BD pero el frontend espera
    public function getSubjectAttribute()
    {
        return 'Tutoría Académica'; // Valor por defecto
    }

    public function getTopicAttribute()
    {
        return 'Asesoría'; // Valor por defecto
    }

    public function getNotesAttribute()
    {
        return null;
    }

    public function getStudentNameAttribute()
    {
        return $this->student ? $this->student->name : null;
    }

    public function getStudentIdAttribute()
    {
        // Devolver el student_id como studentId para el frontend
        return $this->attributes['student_id'] ?? null;
    }

    // Relación con el estudiante (User)
    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    // Relación con el profesor (User)
    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}
