<?php

namespace App\Http\Controllers\Manage;

use App\Exports\AuditLogsExport;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $filters = $this->validatedFilters($request);

        $logs = $this->query($filters)
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('manage.audit_logs.index', [
            'logs' => $logs,
            'users' => User::orderBy('name')->get(['id', 'name']),
            'actions' => AuditLog::query()->select('action')->distinct()->orderBy('action')->pluck('action'),
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $filters = $this->validatedFilters($request);
        $fileName = 'audit_logs_'.now()->format('Ymd_His').'.xlsx';

        return Excel::download(new AuditLogsExport($this->query($filters)->latest()), $fileName, ExcelWriter::XLSX);
    }

    public function destroySelected(Request $request)
    {
        $filters = $this->validatedFilters($request);
        $data = $request->validate([
            'audit_log_ids' => ['required', 'array', 'min:1'],
            'audit_log_ids.*' => ['integer', Rule::exists('audit_logs', 'id')],
        ]);

        $deleted = AuditLog::query()
            ->whereIn('id', array_unique($data['audit_log_ids']))
            ->delete();

        return redirect()
            ->route('manage.audit-logs.index', $this->redirectParameters($request, $filters))
            ->with('success', $deleted.' audit '.str('log')->plural($deleted).' deleted.');
    }

    public function destroyMatching(Request $request)
    {
        $filters = $this->validatedFilters($request);
        $request->validate([
            'confirm_delete_matching' => ['required', 'accepted'],
        ]);

        $deleted = $this->query($filters)->delete();

        return redirect()
            ->route('manage.audit-logs.index', $this->redirectParameters($request, $filters))
            ->with('success', $deleted.' matching audit '.str('log')->plural($deleted).' deleted.');
    }

    private function query(array $filters): Builder
    {
        return AuditLog::query()
            ->with('user')
            ->when($filters['action'] ?? null, fn ($query, $action) => $query->where('action', $action))
            ->when($filters['user_id'] ?? null, fn ($query, $userId) => $query->where('user_id', $userId))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date));
    }

    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'action' => ['nullable', 'string', 'max:255'],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
    }

    private function redirectParameters(Request $request, array $filters): array
    {
        $page = $request->integer('page');

        return $page > 1 ? array_merge($filters, ['page' => $page]) : $filters;
    }
}
