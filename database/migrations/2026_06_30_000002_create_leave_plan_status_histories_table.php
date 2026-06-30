<?php

use App\Models\LeavePlan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LEAVE_PLAN_HISTORY_ACTIONS = [
        'leave_plan_submitted',
        'leave_plan_resubmitted',
        'leave_plan_stage_approved',
        'leave_plan_approved',
        'leave_plan_rejected',
        'leave_plan_cancellation_requested',
        'leave_plan_cancellation_approved',
        'leave_plan_cancellation_rejected',
        'leave_plan_approved_recalled',
        'leave_plan_voided',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('leave_plan_status_histories')) {
            Schema::create('leave_plan_status_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('leave_plan_id')->constrained()->cascadeOnDelete();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action');
                $table->string('old_status')->nullable();
                $table->string('new_status')->nullable();
                $table->string('old_approval_stage', 30)->nullable();
                $table->string('new_approval_stage', 30)->nullable();
                $table->text('comment')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('occurred_at')->nullable();
                $table->timestamps();

                $table->index(['leave_plan_id', 'occurred_at']);
                $table->index('action');
                $table->index('actor_id');
            });
        }

        $this->backfillFromAuditLogs();
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_plan_status_histories');
    }

    private function backfillFromAuditLogs(): void
    {
        DB::table('audit_logs')
            ->join('leave_plans', 'audit_logs.auditable_id', '=', 'leave_plans.id')
            ->leftJoin('users', 'audit_logs.user_id', '=', 'users.id')
            ->select('audit_logs.*', 'users.id as existing_actor_id')
            ->where('auditable_type', LeavePlan::class)
            ->whereIn('action', self::LEAVE_PLAN_HISTORY_ACTIONS)
            ->orderBy('audit_logs.id')
            ->chunkById(200, function ($logs) {
                foreach ($logs as $log) {
                    $oldValues = $this->decodeJson($log->old_values);
                    $newValues = $this->decodeJson($log->new_values);
                    $metadata = json_encode([
                        'source' => 'audit_log_backfill',
                        'audit_log_id' => (string) $log->id,
                    ]);

                    if ($this->backfilledAuditLogExists($log, $metadata)) {
                        continue;
                    }

                    DB::table('leave_plan_status_histories')->insert([
                        'leave_plan_id' => $log->auditable_id,
                        'actor_id' => $log->existing_actor_id,
                        'action' => $log->action,
                        'old_status' => $oldValues['status'] ?? null,
                        'new_status' => $newValues['status'] ?? null,
                        'old_approval_stage' => $oldValues['approval_stage'] ?? null,
                        'new_approval_stage' => $newValues['approval_stage'] ?? null,
                        'comment' => $this->commentFrom($newValues),
                        'ip_address' => $log->ip_address,
                        'metadata' => $metadata,
                        'occurred_at' => $log->created_at,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }, 'audit_logs.id', 'id');
    }

    private function backfilledAuditLogExists(object $log, string $metadata): bool
    {
        return DB::table('leave_plan_status_histories')
            ->where('leave_plan_id', $log->auditable_id)
            ->where('action', $log->action)
            ->where('occurred_at', $log->created_at)
            ->where(function ($query) use ($log, $metadata) {
                $query->where('metadata', $metadata)
                    ->orWhere('metadata', 'like', '%"audit_log_id":"'.$log->id.'"%')
                    ->orWhere('metadata', 'like', '%"audit_log_id":'.$log->id.'%');
            })
            ->exists();
    }

    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function commentFrom(array $values): ?string
    {
        return $values['recall_reason']
            ?? $values['cancellation_reason']
            ?? $values['cancellation_rejection_comment']
            ?? $values['rejection_comment']
            ?? $values['void_reason']
            ?? null;
    }
};
