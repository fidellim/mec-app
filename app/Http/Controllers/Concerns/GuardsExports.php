<?php

namespace App\Http\Controllers\Concerns;

use Closure;
use Illuminate\Support\Facades\Cache;

trait GuardsExports
{
    protected function guardedExport(Closure $callback)
    {
        $lock = Cache::lock('exports:user:'.auth()->id(), 120);

        if (! $lock->get()) {
            return back()->with('warning', 'An export is already running. Please wait for it to finish before starting another export.');
        }

        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }
}
