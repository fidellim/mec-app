<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ANNUAL = 'L100';
    private const SICK = 'L110';
    private const MATERNITY = 'L160';
    private const PARENTAL = 'L170';
    private const BEREAVEMENT = 'L180';
    private const SERVICE_INCENTIVE = 'L190';

    public function up(): void
    {
        Schema::create('leave_entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->string('attendance_code', 20);
            $table->decimal('allowance_days', 6, 2)->default(0);
            $table->decimal('claimable_allowance_days', 6, 2)->default(0);
            $table->string('source', 40)->default('regional_default');
            $table->string('region', 20);
            $table->string('setting_key')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'year', 'attendance_code'], 'leave_entitlements_user_year_code_unique');
            $table->index(['year', 'attendance_code']);
        });

        $this->backfillCurrentYear();
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_entitlements');
    }

    private function backfillCurrentYear(): void
    {
        $year = (int) now()->year;
        $now = now();

        DB::table('users')
            ->whereIn('role', ['employee', 'hod'])
            ->where('is_active', true)
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($year, $now) {
                foreach ($users as $user) {
                    foreach ($this->eligibleCodesFor($user) as $attendanceCode) {
                        $attributes = $this->entitlementAttributesFor($user, $year, $attendanceCode);

                        DB::table('leave_entitlements')->updateOrInsert(
                            [
                                'user_id' => $user->id,
                                'year' => $year,
                                'attendance_code' => $attendanceCode,
                            ],
                            $attributes + [
                                'created_at' => $now,
                                'updated_at' => $now,
                            ],
                        );
                    }
                }
            });
    }

    private function eligibleCodesFor(object $user): array
    {
        return collect([
            self::ANNUAL,
            self::SICK,
            self::MATERNITY,
            self::PARENTAL,
            self::BEREAVEMENT,
            self::SERVICE_INCENTIVE,
        ])
            ->filter(function (string $attendanceCode) use ($user) {
                return match ($attendanceCode) {
                    self::MATERNITY => $user->gender === 'female',
                    self::PARENTAL => $user->marital_status === 'married',
                    self::SERVICE_INCENTIVE => $this->regionFor($user) === 'ph',
                    default => true,
                };
            })
            ->values()
            ->all();
    }

    private function entitlementAttributesFor(object $user, int $year, string $attendanceCode): array
    {
        $region = $this->regionFor($user);
        $settingKey = $this->settingKeyFor($region, $attendanceCode);
        $claimable = $this->claimableAllowanceFor($user, $year, $attendanceCode, $settingKey, $region);
        $allowance = $this->visibleAllowanceFor($region, $attendanceCode, $claimable);
        $usesOverride = $attendanceCode === self::ANNUAL
            && $year === (int) now()->year
            && $user->annual_leave_allowance_days !== null;

        return [
            'allowance_days' => $allowance,
            'claimable_allowance_days' => $claimable,
            'source' => $usesOverride ? 'user_override' : 'regional_default',
            'region' => $region,
            'setting_key' => $settingKey,
            'notes' => $usesOverride ? 'Current-year annual leave override migrated from user profile.' : null,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    private function claimableAllowanceFor(object $user, int $year, string $attendanceCode, string $settingKey, string $region): float
    {
        if (
            $attendanceCode === self::ANNUAL
            && $year === (int) now()->year
            && $user->annual_leave_allowance_days !== null
        ) {
            return (float) $user->annual_leave_allowance_days;
        }

        return (float) (DB::table('leave_settings')->where('key', $settingKey)->value('decimal_value')
            ?? $this->fallbackAllowanceFor($region, $attendanceCode));
    }

    private function visibleAllowanceFor(string $region, string $attendanceCode, float $claimable): float
    {
        if ($region === 'uae' && $attendanceCode === self::SICK) {
            return min(15.0, $claimable);
        }

        if ($region === 'uae' && $attendanceCode === self::MATERNITY) {
            return min(45.0, $claimable);
        }

        return $claimable;
    }

    private function settingKeyFor(string $region, string $attendanceCode): string
    {
        return match ($attendanceCode) {
            self::SICK => $region === 'ph' ? 'sick_leave_default_days_ph' : 'sick_leave_default_days_uae',
            self::MATERNITY => $region === 'ph' ? 'maternity_leave_default_days_ph' : 'maternity_leave_default_days_uae',
            self::PARENTAL => $region === 'ph' ? 'parental_leave_default_days_ph' : 'parental_leave_default_days_uae',
            self::BEREAVEMENT => $region === 'ph' ? 'bereavement_compassionate_leave_default_days_ph' : 'bereavement_compassionate_leave_default_days_uae',
            self::SERVICE_INCENTIVE => 'service_incentive_leave_default_days_ph',
            default => $region === 'ph' ? 'annual_leave_default_days_ph' : 'annual_leave_default_days_uae',
        };
    }

    private function fallbackAllowanceFor(string $region, string $attendanceCode): float
    {
        return match ($attendanceCode) {
            self::SICK => $region === 'ph' ? 5.0 : 90.0,
            self::MATERNITY => 60.0,
            self::PARENTAL => 5.0,
            self::BEREAVEMENT => 8.0,
            self::SERVICE_INCENTIVE => 5.0,
            default => $region === 'ph' ? 5.0 : 22.0,
        };
    }

    private function regionFor(object $user): string
    {
        return is_string($user->employee_code) && str_starts_with($user->employee_code, 'MEC-PHIL-HR-')
            ? 'ph'
            : 'uae';
    }
};
