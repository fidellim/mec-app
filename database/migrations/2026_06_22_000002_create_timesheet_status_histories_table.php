<?php

use App\Models\Timesheet;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TIMESHEET_HISTORY_ACTIONS = [
        'timesheet_submitted',
        'timesheet_resubmitted',
        'timesheet_approved',
        'timesheet_rejected',
        'timesheet_withdrawn',
        'timesheet_approved_recalled',
        'timesheet_voided',
    ];

    public function up(): void
    {
        Schema::create('timesheet_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('timesheet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('old_status')->nullable();
            $table->string('new_status')->nullable();
            $table->text('comment')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();

            $table->index(['timesheet_id', 'occurred_at']);
            $table->index('action');
            $table->index('actor_id');
        });

        $this->backfillFromAuditLogs();
    }

    public function down(): void
    {
        Schema::dropIfExists('timesheet_status_histories');
    }

    private function backfillFromAuditLogs(): void
    {
        DB::table('audit_logs')
            ->where('auditable_type', Timesheet::class)
            ->whereIn('action', self::TIMESHEET_HISTORY_ACTIONS)
            ->orderBy('id')
            ->chunkById(200, function ($logs) {
                foreach ($logs as $log) {
                    $oldValues = $this->decodeJson($log->old_values);
                    $newValues = $this->decodeJson($log->new_values);

                    DB::table('timesheet_status_histories')->insert([
                        'timesheet_id' => $log->auditable_id,
                        'actor_id' => $log->user_id,
                        'action' => $log->action,
                        'old_status' => $oldValues['status'] ?? null,
                        'new_status' => $newValues['status'] ?? null,
                        'comment' => $this->commentFrom($newValues),
                        'ip_address' => $log->ip_address,
                        'metadata' => json_encode([
                            'source' => 'audit_log_backfill',
                            'audit_log_id' => $log->id,
                        ]),
                        'occurred_at' => $log->created_at,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
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
            ?? $values['withdrawal_comment']
            ?? $values['rejection_comment']
            ?? $values['void_reason']
            ?? null;
    }
};
