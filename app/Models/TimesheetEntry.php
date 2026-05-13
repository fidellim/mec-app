<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimesheetEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'timesheet_id', 'work_date', 'day_name', 'attendance_code', 'project_id', 'regular_hours',
        'overtime_hours', 'description', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'regular_hours' => 'decimal:2',
            'overtime_hours' => 'decimal:2',
        ];
    }

    public function timesheet()
    {
        return $this->belongsTo(Timesheet::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
