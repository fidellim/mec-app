<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'code', 'hod_id'];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function hod()
    {
        return $this->belongsTo(User::class, 'hod_id');
    }

    public function timesheets()
    {
        return $this->hasMany(Timesheet::class);
    }
}
