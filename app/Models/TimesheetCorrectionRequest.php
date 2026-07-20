<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimesheetCorrectionRequest extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_DISMISSED = 'dismissed';
    public const STATUS_WITHDRAWN = 'withdrawn';
    public const STATUS_SUPERSEDED = 'superseded';

    protected $fillable = ['timesheet_id', 'requested_by', 'department_id', 'status', 'comment', 'resolved_by', 'resolution_comment', 'resolved_at', 'superseded_by_request_id', 'authority_role'];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function timesheet() { return $this->belongsTo(Timesheet::class); }
    public function requester() { return $this->belongsTo(User::class, 'requested_by'); }
    public function resolver() { return $this->belongsTo(User::class, 'resolved_by'); }
    public function entries() { return $this->hasMany(TimesheetCorrectionRequestEntry::class); }
}
