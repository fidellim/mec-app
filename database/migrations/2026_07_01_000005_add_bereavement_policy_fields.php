<?php

use App\Models\LeaveSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('leave_plans') && ! Schema::hasColumn('leave_plans', 'bereavement_relationship')) {
            Schema::table('leave_plans', function (Blueprint $table) {
                $table->string('bereavement_relationship', 40)->nullable()->after('half_day_period');
            });
        }

        if (! Schema::hasTable('leave_settings')) {
            return;
        }

        $now = now();

        foreach ($this->settingDefaults() as $key => $attributes) {
            DB::table('leave_settings')->updateOrInsert(
                ['key' => $key],
                $attributes + [
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('leave_settings')) {
            DB::table('leave_settings')
                ->whereIn('key', array_keys($this->settingDefaults()))
                ->delete();
        }

        if (Schema::hasTable('leave_plans') && Schema::hasColumn('leave_plans', 'bereavement_relationship')) {
            Schema::table('leave_plans', function (Blueprint $table) {
                $table->dropColumn('bereavement_relationship');
            });
        }
    }

    private function settingDefaults(): array
    {
        return [
            LeaveSetting::BEREAVEMENT_SPOUSE_LEAVE_DAYS_UAE => [
                'name' => 'UAE Bereavement Leave - Spouse Death Days',
                'description' => 'Maximum L180 bereavement / compassionate leave days per UAE spouse-death request.',
                'decimal_value' => 5,
            ],
            LeaveSetting::BEREAVEMENT_IMMEDIATE_FAMILY_LEAVE_DAYS_UAE => [
                'name' => 'UAE Bereavement Leave - Immediate Family Death Days',
                'description' => 'Maximum L180 bereavement / compassionate leave days per UAE immediate-family death request.',
                'decimal_value' => 3,
            ],
        ];
    }
};
