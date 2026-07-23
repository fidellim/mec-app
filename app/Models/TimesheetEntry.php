<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimesheetEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'timesheet_id', 'work_date', 'day_name', 'attendance_code', 'project_id', 'department_id', 'regular_hours',
        'overtime_hours', 'description', 'remarks', 'manpower_category_snapshot', 'allocation_bucket_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'timesheet_id' => 'integer',
            'project_id' => 'integer',
            'department_id' => 'integer',
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

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function correctionRequestEntries()
    {
        return $this->hasMany(TimesheetCorrectionRequestEntry::class);
    }
}
