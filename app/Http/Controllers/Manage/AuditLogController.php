<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;

class AuditLogController extends Controller
{
    public function index()
    {
        $logs = AuditLog::query()
            ->with('user')
            ->when(request('action'), fn ($query, $action) => $query->where('action', $action))
            ->when(request('user_id'), fn ($query, $userId) => $query->where('user_id', $userId))
            ->when(request('date_from'), fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when(request('date_to'), fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('manage.audit_logs.index', [
            'logs' => $logs,
            'users' => User::orderBy('name')->get(['id', 'name']),
            'actions' => AuditLog::query()->select('action')->distinct()->orderBy('action')->pluck('action'),
        ]);
    }
}
