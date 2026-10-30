<?php

namespace App\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class HistoricalTimesheetImportStore
{
    public function directory(): string
    {
        $directory = config('timesheet_import.directory');
        File::ensureDirectoryExists($directory, 0700, true);

        return $directory;
    }

    public function create(int $actorId, array $items): string
    {
        $id = (string) Str::uuid();
        $path = $this->directory().'/'.$id.'.batch';
        File::put($path, $this->encode([
            'actor_id' => $actorId, 'expires_at' => now()->addMinutes(config('timesheet_import.expires_after_minutes'))->timestamp,
            'revision' => 1, 'items' => $items, 'results' => null,
        ]));
        chmod($path, 0600);

        return $id;
    }

    /** A process-held lock protects editing, confirmation and cancellation, including repeated requests. */
    public function access(string $id, int $actorId, callable $callback): mixed
    {
        abort_unless(Str::isUuid($id), 404);
        $path = $this->directory().'/'.$id.'.batch';
        $handle = @fopen($path, 'r+');
        abort_unless($handle, 410, 'This import has expired. Upload the workbooks again.');
        try {
            abort_unless(flock($handle, LOCK_EX | LOCK_NB), 409, 'This import is being processed. Please try again.');
            $batch = json_decode(Crypt::decryptString(stream_get_contents($handle)), true, flags: JSON_THROW_ON_ERROR);
            abort_unless($batch['actor_id'] === $actorId, 403);
            if ($batch['expires_at'] <= now()->timestamp) {
                @unlink($path);
                abort(410, 'This import has expired. Upload the workbooks again.');
            }
            $result = $callback($batch);
            rewind($handle);
            $encoded = $this->encode($batch);
            // Write before truncating so a failure cannot silently turn a batch into an empty file.
            if (fwrite($handle, $encoded) !== strlen($encoded) || ! ftruncate($handle, strlen($encoded)) || ! fflush($handle)) {
                throw new \RuntimeException('Unable to save the import preview.');
            }

            return $result;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    public function prune(): int
    {
        $removed = 0;
        foreach (File::files($this->directory()) as $file) {
            if ($file->getExtension() === 'batch') {
                $handle = @fopen($file->getPathname(), 'r+');
                if (! $handle) {
                    continue;
                }
                try {
                    if (! flock($handle, LOCK_EX | LOCK_NB)) {
                        continue;
                    }
                    try {
                        $batch = json_decode(Crypt::decryptString(stream_get_contents($handle)), true, flags: JSON_THROW_ON_ERROR);
                    } catch (DecryptException|\JsonException $exception) {
                        // A corrupted preview must not prevent cleanup of the other uploads.
                        if ($file->getMTime() < now()->subHour()->timestamp) {
                            $removed += (int) unlink($file->getPathname());
                        }

                        continue;
                    }
                    if (($batch['expires_at'] ?? 0) <= now()->timestamp) {
                        $removed += (int) unlink($file->getPathname());
                    }
                } finally {
                    fclose($handle);
                }
            } elseif ($file->getExtension() === 'upload' && $file->getMTime() < now()->subHour()->timestamp) {
                $removed += (int) unlink($file->getPathname());
            }
        }

        return $removed;
    }

    private function encode(array $batch): string
    {
        return Crypt::encryptString(json_encode($batch, JSON_THROW_ON_ERROR));
    }
}
