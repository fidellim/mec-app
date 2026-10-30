<?php

return [
    'directory' => env('TIMESHEET_IMPORT_DIRECTORY', storage_path('app/private/timesheet-imports')),
    'expires_after_minutes' => 60,
    'max_files' => 20,
    'max_file_kb' => 5120,
    'max_batch_kb' => 20480,
    'max_preview_kb' => 8192,
];
