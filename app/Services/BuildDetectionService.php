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
        $commit = Process::path(base_path())->run('git rev-parse HEAD');

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
