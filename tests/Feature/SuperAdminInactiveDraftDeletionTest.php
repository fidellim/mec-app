<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Timesheet;
use App\Services\AuditLogService;
use App\Services\DashboardSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\CreatesTimesheetData;
use Tests\TestCase;

class SuperAdminInactiveDraftDeletionTest extends TestCase
{
    use CreatesTimesheetData;
    use RefreshDatabase;

    private function draft(bool $active = false, string $status = 'draft'): Timesheet
    {
        $owner = $this->userWithRole('employee', [
            'department_id' => $this->department()->id,
            'is_active' => $active,
        ]);

        return $this->submittedTimesheet($owner, $this->openPeriod(), $this->project(), [
            'status' => $status,
            'submitted_at' => null,
        ]);
    }

    public function test_super_admin_can_delete_inactive_owners_draft_with_surviving_audit_snapshot(): void
    {
        $draft = $this->draft();
        $entry = $draft->entries()->firstOrFail();
        $actor = $this->userWithRole('super_admin');

        $this->actingAs($actor)->withServerVariables(['REMOTE_ADDR' => '10.11.12.13'])
            ->delete(route('admin.timesheets.destroy-draft', $draft), ['deletion_reason' => 'Employee has left the company.'])
            ->assertRedirect(route('admin.timesheets.index'))->assertSessionHas('success');

        $this->assertModelMissing($draft);
        $this->assertModelMissing($entry);
        $this->assertFalse($draft->user->fresh()->is_active);
        $log = AuditLog::where('action', 'inactive_user_timesheet_draft_deleted')->sole();
        $this->assertSame($actor->id, $log->user_id);
        $this->assertSame(Timesheet::class, $log->auditable_type);
        $this->assertSame($draft->id, $log->auditable_id);
        $this->assertSame('10.11.12.13', $log->ip_address);
        $this->assertSame($draft->user->name, $log->old_values['owner']['name']);
        $this->assertSame($draft->user->employee_code, $log->old_values['owner']['employee_code']);
        $this->assertArrayNotHasKey('password', $log->old_values['owner']);
        $this->assertSame(20, $log->old_values['period']['week_number']);
        $this->assertSame('8.00', $log->old_values['total_hours']);
        $this->assertSame($entry->id, $log->old_values['entries'][0]['id']);
        $this->assertSame($entry->project->project_code, $log->old_values['entries'][0]['project']['project_code']);
        $this->assertSame('Employee has left the company.', $log->new_values['deletion_reason']);
        $this->actingAs($actor)->get(route('manage.audit-logs.index', ['action' => $log->action]))
            ->assertOk()->assertSee('Employee has left the company.');
    }

    public static function unauthorizedRoles(): array
    {
        return [['admin', true], ['hod', true], ['employee', true], ['super_admin', false]];
    }

    #[DataProvider('unauthorizedRoles')]
    public function test_only_active_super_admins_can_delete(string $role, bool $active): void
    {
        $draft = $this->draft();
        $actor = $this->userWithRole($role, ['is_active' => $active]);
        $this->actingAs($actor)->delete(route('admin.timesheets.destroy-draft', $draft), ['deletion_reason' => 'Cleanup draft.'])
            ->assertForbidden();
        $this->assertModelExists($draft);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_guests_cannot_delete(): void
    {
        $draft = $this->draft();
        $this->delete(route('admin.timesheets.destroy-draft', $draft), ['deletion_reason' => 'Cleanup draft.'])
            ->assertRedirect(route('login'));
        $this->assertModelExists($draft);
    }

    public static function otherStatuses(): array
    {
        return array_map(fn ($status) => [$status], ['submitted', 'approved', 'rejected', 'withdrawn', 'recalled', 'voided']);
    }

    #[DataProvider('otherStatuses')]
    public function test_non_drafts_cannot_be_deleted(string $status): void
    {
        $draft = $this->draft(false, $status);
        $this->actingAs($this->userWithRole('super_admin'))
            ->delete(route('admin.timesheets.destroy-draft', $draft), ['deletion_reason' => 'Cleanup draft.'])
            ->assertStatus(422);
        $this->assertModelExists($draft);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_reactivated_owner_is_rechecked_after_opening_confirmation(): void
    {
        $draft = $this->draft();
        $this->actingAs($this->userWithRole('super_admin'))
            ->get(route('admin.timesheets.show', $draft))->assertOk()->assertSee('id="deleteDraftForm"', false);
        $draft->user->update(['is_active' => true]);
        $this->delete(route('admin.timesheets.destroy-draft', $draft), ['deletion_reason' => 'Cleanup draft.'])->assertStatus(422);
        $this->assertModelExists($draft);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->get(route('admin.timesheets.show', $draft))->assertOk()->assertDontSee('id="deleteDraftForm"', false);
    }

    public function test_changed_status_is_rechecked_after_opening_confirmation(): void
    {
        $draft = $this->draft();
        $this->actingAs($this->userWithRole('super_admin'))
            ->get(route('admin.timesheets.show', $draft))->assertOk()->assertSee('id="deleteDraftForm"', false);
        $draft->update(['status' => 'submitted']);
        $this->delete(route('admin.timesheets.destroy-draft', $draft), ['deletion_reason' => 'Cleanup draft.'])->assertStatus(422);
        $this->assertModelExists($draft);
        $this->get(route('admin.timesheets.show', $draft))->assertOk()->assertDontSee('id="deleteDraftForm"', false);
    }

    public function test_regular_admin_cannot_see_delete_action(): void
    {
        $draft = $this->draft();
        $this->actingAs($this->userWithRole('admin'))->get(route('admin.timesheets.show', $draft))
            ->assertOk()->assertDontSee('id="deleteDraftForm"', false);
    }

    public static function invalidReasons(): array
    {
        return [[null], [''], ['    '], ['tiny'], [str_repeat('x', 2001)]];
    }

    #[DataProvider('invalidReasons')]
    public function test_reason_is_required_and_bounded(?string $reason): void
    {
        $draft = $this->draft();
        $this->actingAs($this->userWithRole('super_admin'))->from(route('admin.timesheets.show', $draft))
            ->delete(route('admin.timesheets.destroy-draft', $draft), ['deletion_reason' => $reason])
            ->assertRedirect(route('admin.timesheets.show', $draft))->assertSessionHasErrors('deletion_reason');
        $this->assertModelExists($draft);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_audit_failure_keeps_draft_and_entries(): void
    {
        $draft = $this->draft();
        $entry = $draft->entries()->firstOrFail();
        $this->mock(AuditLogService::class, function ($mock) {
            $mock->shouldReceive('record')->once()->andThrow(new RuntimeException('Audit unavailable'));
        });
        $this->actingAs($this->userWithRole('super_admin'))
            ->delete(route('admin.timesheets.destroy-draft', $draft), ['deletion_reason' => 'Cleanup draft.'])->assertStatus(500);
        $this->assertModelExists($draft);
        $this->assertModelExists($entry);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_deletion_failure_rolls_back_audit_and_cascaded_entries(): void
    {
        $draft = $this->draft();
        $entry = $draft->entries()->firstOrFail();
        $this->mock(DashboardSummaryService::class, function ($mock) {
            $mock->shouldReceive('forgetForTimesheet')->once()->andThrow(new RuntimeException('Deletion hook failed'));
        });
        $this->actingAs($this->userWithRole('super_admin'))
            ->delete(route('admin.timesheets.destroy-draft', $draft), ['deletion_reason' => 'Cleanup draft.'])->assertStatus(500);
        $this->assertModelExists($draft);
        $this->assertModelExists($entry);
        $this->assertDatabaseCount('audit_logs', 0);
    }
}
