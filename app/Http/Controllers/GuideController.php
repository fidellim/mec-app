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
        $fileName = self::ROLE_GUIDES[$user->role] ?? null;

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
}
