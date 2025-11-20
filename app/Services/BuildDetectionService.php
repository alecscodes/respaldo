<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;

class BuildDetectionService
{
    /**
     * Get current git commit hash.
     */
    public function getCurrentCommit(): ?string
    {
        $workingDir = base_path();

        // Configure git safe directory for Docker environments
        Process::path($workingDir)->run('git config --global --add safe.directory '.escapeshellarg($workingDir));

        $commit = Process::path($workingDir)->run('git rev-parse HEAD');

        return $commit->successful() ? trim($commit->output()) : null;
    }

    /**
     * Get short commit hash (7 chars like git).
     */
    public function getShortCommit(): ?string
    {
        $commit = $this->getCurrentCommit();

        return $commit ? substr($commit, 0, 7) : null;
    }
}
