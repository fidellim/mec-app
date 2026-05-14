<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimesheetPeriod extends Model
{
    use HasFactory;

    protected $fillable = ['week_number', 'year', 'start_date', 'end_date', 'status'];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'week_number' => 'integer',
            'year' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function timesheets()
    {
        return $this->hasMany(Timesheet::class);
    }
}
