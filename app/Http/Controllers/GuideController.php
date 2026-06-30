<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class GuideController extends Controller
{
    private const ROLE_GUIDES = [
        'employee' => 'EMPLOYEE_ONBOARDING.md',
        'hod' => 'HOD_ONBOARDING.md',
        'admin' => 'ADMIN_ONBOARDING.md',
        'super_admin' => 'SUPER_ADMIN_ONBOARDING.md',
    ];

    public function __invoke()
    {
        $user = auth()->user();
        $fileName = $this->guideFileNameFor($user);

        abort_unless($fileName, 404);

        $path = base_path('docs/'.$fileName);

        abort_unless(File::isFile($path), 404, 'Guide content is not available.');

        $markdown = File::get($path);
        $html = Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        return view('guide.show', [
            'guideHtml' => $html,
            'roleLabel' => config('roles.labels.'.$user->role, Str::headline($user->role)),
            'updatedAt' => File::lastModified($path),
        ]);
    }

    private function guideFileNameFor($user): ?string
    {
        if ($this->isPhilippinesUser($user)) {
            return match ($user->role) {
                'employee' => 'PH_EMPLOYEE_ONBOARDING.md',
                'hod' => 'PH_HOD_ONBOARDING.md',
                default => self::ROLE_GUIDES[$user->role] ?? null,
            };
        }

        return self::ROLE_GUIDES[$user->role] ?? null;
    }

    private function isPhilippinesUser($user): bool
    {
        return is_string($user->employee_code)
            && str_starts_with($user->employee_code, 'MEC-PHIL-HR-');
    }
}
