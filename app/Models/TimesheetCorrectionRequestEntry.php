<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimesheetCorrectionRequestEntry extends Model
{
    protected $fillable = ['timesheet_correction_request_id', 'timesheet_entry_id', 'project_id', 'work_date', 'project_code', 'regular_hours', 'overtime_hours', 'description', 'entry_comment', 'suggested_regular_hours', 'suggested_overtime_hours'];

    protected function casts(): array
    {
        return ['work_date' => 'date', 'regular_hours' => 'decimal:2', 'overtime_hours' => 'decimal:2', 'suggested_regular_hours' => 'decimal:2', 'suggested_overtime_hours' => 'decimal:2'];
    }

    public function request() { return $this->belongsTo(TimesheetCorrectionRequest::class, 'timesheet_correction_request_id'); }
    public function timesheetEntry() { return $this->belongsTo(TimesheetEntry::class); }
    public function project() { return $this->belongsTo(Project::class); }
}
