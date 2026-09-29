<?php

namespace App\Support;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ListContext
{
    /** Keep list navigation local to the current URL, never an arbitrary return URL. */
    public static function filters(string $resource, bool $fromIndex = false): array
    {
        $input = $fromIndex ? request()->query() : request()->input('list', []);
        if (! is_array($input)) {
            return [];
        }

        $rules = ['page' => ['integer', 'min:1', 'max:2147483647']];
        if ($resource === 'users') {
            $roles = auth()->user()?->role === 'admin' ? ['admin', 'hod', 'employee'] : array_keys(config('roles.labels'));
            $rules += [
                'role' => ['string', Rule::in($roles)],
                'region' => ['string', Rule::in(['uae', 'ph', 'unknown'])],
                'department_id' => ($input['department_id'] ?? null) === 'unassigned'
                    ? ['string', Rule::in(['unassigned'])]
                    : ['integer', 'exists:departments,id'],
                'search' => is_array($input['search'] ?? null) ? ['array', 'max:100'] : ['string', 'max:100'],
            ];
        } else {
            $rules += ['search' => ['string', 'max:100'], 'status' => ['string', Rule::in(['active', 'inactive'])]];
        }

        $filters = [];
        foreach ($rules as $key => $rule) {
            if (! array_key_exists($key, $input) || $input[$key] === null || $input[$key] === '') {
                continue;
            }
            $fieldRules = [$key => $rule];
            if ($key === 'search' && is_array($input[$key])) {
                $fieldRules['search.*'] = ['string', 'max:100'];
            }
            if (Validator::make([$key => $input[$key]], $fieldRules)->passes()) {
                $filters[$key] = is_array($input[$key]) ? array_values($input[$key]) : $input[$key];
            }
        }

        return array_replace(array_intersect_key($input, $filters), $filters);
    }

    public static function parameters(string $resource, bool $fromIndex = false): array
    {
        $filters = self::filters($resource, $fromIndex);

        return $filters ? ['list' => $filters] : [];
    }
}
