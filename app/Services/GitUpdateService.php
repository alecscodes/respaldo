<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;

class GitUpdateService
{
    protected const array COMPOSER_FILES = ['composer.json', 'composer.lock'];

    protected const array NPM_FILES = ['package.json', 'package-lock.json'];

    protected const array FRONTEND_FILES = [
        'resources/js/',
        'resources/css/',
        'vite.config.ts',
        'vite.config.js',
        'tsconfig.json',
        'tailwind.config.js',
        'tailwind.config.ts',
        'postcss.config.js',
        'postcss.config.ts',
        'eslint.config.js',
        'eslint.config.ts',
    ];

    /**
     * Check if updates are available from the remote repository.
     *
     * @return array{available: bool, current_commit: string|null, remote_commit: string|null, commits_behind: int, error: string|null}
     */
    public function checkForUpdates(): array
    {
        $result = [
            'available' => false,
            'current_commit' => null,
            'remote_commit' => null,
            'commits_behind' => 0,
            'error' => null,
        ];

        try {
            $workingDir = base_path();

            // Check if git is available
            $gitCheck = Process::run('git --version');
            if (! $gitCheck->successful()) {
                $result['error'] = 'Git is not available';

                return $result;
            }

            // Check if .git directory exists
            if (! is_dir($workingDir.'/.git')) {
                $result['error'] = 'Git repository not found';

                return $result;
            }

            // Configure Git to trust this directory (fixes Docker ownership issues)
            $this->configureGitSafeDirectory($workingDir);

            // Check if we're in a git repository
            $gitDirCheck = Process::path($workingDir)->run('git rev-parse --git-dir');
            if (! $gitDirCheck->successful()) {
                $result['error'] = 'Not a git repository: '.$gitDirCheck->errorOutput();

                return $result;
            }

            // Get current commit hash
            $currentCommit = Process::path($workingDir)->run('git rev-parse HEAD');
            if ($currentCommit->successful()) {
                $result['current_commit'] = trim($currentCommit->output());
            }

            // Fetch latest changes from remote (without merging)
            $fetch = Process::path($workingDir)->run('git fetch origin --quiet');
            if (! $fetch->successful()) {
                $result['error'] = 'Failed to fetch from remote: '.$fetch->errorOutput();

                return $result;
            }

            // Get remote branch name (default to main)
            $branch = $this->getCurrentBranch($workingDir);

            // Check commits behind
            $commitsBehind = Process::path($workingDir)->run("git rev-list --count HEAD..origin/{$branch}");
            if ($commitsBehind->successful()) {
                $result['commits_behind'] = (int) trim($commitsBehind->output());
            }

            // Get remote commit hash
            $remoteCommit = Process::path($workingDir)->run("git rev-parse origin/{$branch}");
            if ($remoteCommit->successful()) {
                $result['remote_commit'] = trim($remoteCommit->output());
            }

            $result['available'] = $result['commits_behind'] > 0;
        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Perform the update by pulling latest changes.
     *
     * @return array{success: bool, message: string, output: string|null, error: string|null}
     */
    public function performUpdate(): array
    {
        $result = [
            'success' => false,
            'message' => '',
            'output' => null,
            'error' => null,
        ];

        try {
            $workingDir = base_path();

            // Use deploy.sh for non-Docker deployments
            // For Docker, use manual process (git reset --hard) since deploy.sh does git pull
            if ($this->isRunningInDocker()) {
                return $this->runDockerUpdate($workingDir);
            }

            return $this->runDeployScript($workingDir);
        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
            $result['message'] = 'Update failed: '.$e->getMessage();
        }

        return $result;
    }

    /**
     * Run Docker update process (git reset + full deployment steps).
     *
     * @return array{success: bool, message: string, output: string|null, error: string|null}
     */
    protected function runDockerUpdate(string $workingDir): array
    {
        $result = [
            'success' => false,
            'message' => '',
            'output' => null,
            'error' => null,
        ];

        try {
            $this->configureGitSafeDirectory($workingDir);

            $branch = $this->getCurrentBranch($workingDir);
            $oldCommitHash = $this->getCurrentCommit($workingDir);

            $fetch = Process::path($workingDir)->run('git fetch origin');
            if (! $fetch->successful()) {
                $result['error'] = 'Failed to fetch from remote: '.$fetch->errorOutput();
                $result['message'] = 'Update failed';

                return $result;
            }

            $this->ensureGitPermissions($workingDir);

            $reset = Process::path($workingDir)->run("git reset --hard origin/{$branch}");
            if (! $reset->successful()) {
                $result['error'] = 'Failed to reset to remote: '.$reset->errorOutput();
                $result['message'] = 'Update failed';

                return $result;
            }

            Process::path($workingDir)->run('git clean -fd');

            $result['output'] = trim($reset->output());

            $changedFiles = $this->getChangedFiles($oldCommitHash);
            $this->fixPermissions($changedFiles);

            // Always run full deployment steps (like deploy.sh)
            $this->runFullDeployment($workingDir, $result);

            $result['success'] = true;
            $result['message'] = 'Update completed successfully';
        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
            $result['message'] = 'Update failed: '.$e->getMessage();
        }

        return $result;
    }

    /**
     * Run full deployment steps (composer, npm, build, migrations, optimize).
     *
     * @param  array<string, mixed>  $result
     */
    protected function runFullDeployment(string $workingDir, array &$result): void
    {
        // Install Composer dependencies
        $this->runComposerInstall($workingDir, $result);

        // Install NPM dependencies
        if (file_exists($workingDir.'/package.json')) {
            $this->runNpmInstall($workingDir, $result);
        }

        // Build assets
        if (file_exists($workingDir.'/package.json')) {
            $this->runNpmBuild($workingDir, $result);
        }

        // Run migrations
        $migrate = Process::path($workingDir)->run('php artisan migrate --force');
        if ($migrate->successful()) {
            $result['output'] .= "\n\n=== Migrations ===\n".$migrate->output();
        } else {
            $result['output'] .= "\n\n=== Migrations Failed ===\n".$migrate->errorOutput();
        }

        // Clear and optimize
        $this->clearCaches();

        $optimize = Process::path($workingDir)->run('php artisan optimize');
        if ($optimize->successful()) {
            $result['output'] .= "\n\n=== Optimize ===\n".$optimize->output();
        }

        $dumpAutoload = Process::path($workingDir)->run('composer dump-autoload --optimize --no-interaction');
        if ($dumpAutoload->successful()) {
            $result['output'] .= "\n\n=== Autoload Dump ===\n".$dumpAutoload->output();
        }
    }

    /**
     * Handle dependency updates based on changed files.
     *
     * @param  array<string, mixed>  $result
     */
    protected function handleDependencyUpdates(string $workingDir, array $changedFiles, array &$result): void
    {
        $composerChanged = $this->filesChanged($changedFiles, self::COMPOSER_FILES);
        $npmChanged = $this->filesChanged($changedFiles, self::NPM_FILES);
        $frontendChanged = $this->filesChanged($changedFiles, self::FRONTEND_FILES);

        if ($composerChanged && file_exists(base_path('composer.json'))) {
            $this->runComposerInstall($workingDir, $result);
        }

        if ($npmChanged && file_exists(base_path('package.json'))) {
            $this->runNpmInstall($workingDir, $result);
        }

        if (($frontendChanged || $npmChanged) && file_exists(base_path('package.json'))) {
            $this->runNpmBuild($workingDir, $result);
        }
    }

    /**
     * Run composer install.
     *
     * @param  array<string, mixed>  $result
     */
    protected function runComposerInstall(string $workingDir, array &$result): void
    {
        $composer = Process::path($workingDir)->run('composer install --no-dev --optimize-autoloader --no-interaction --no-scripts');

        if ($composer->successful()) {
            $result['output'] .= "\n\n=== Composer Install ===\n".$composer->output();
        } else {
            $result['output'] .= "\n\n=== Composer Install Failed ===\n".$composer->errorOutput();
        }
    }

    /**
     * Run npm install.
     *
     * @param  array<string, mixed>  $result
     */
    protected function runNpmInstall(string $workingDir, array &$result): void
    {
        $npmInstall = Process::path($workingDir)->run('npm ci');

        if ($npmInstall->successful()) {
            $result['output'] .= "\n\n=== NPM Install ===\n".$npmInstall->output();
        } else {
            $result['output'] .= "\n\n=== NPM Install Failed ===\n".$npmInstall->errorOutput();
        }
    }

    /**
     * Run npm build.
     *
     * @param  array<string, mixed>  $result
     */
    protected function runNpmBuild(string $workingDir, array &$result): void
    {
        $npmBuild = Process::path($workingDir)->run('npm run build');

        if ($npmBuild->successful()) {
            $result['output'] .= "\n\n=== NPM Build ===\n".$npmBuild->output();
        } else {
            $result['output'] .= "\n\n=== NPM Build Failed ===\n".$npmBuild->errorOutput();
        }
    }

    /**
     * Get current commit hash.
     */
    protected function getCurrentCommit(string $workingDir): ?string
    {
        $commit = Process::path($workingDir)->run('git rev-parse HEAD');

        return $commit->successful() ? trim($commit->output()) : null;
    }

    /**
     * Get list of changed files between two commits.
     *
     * @return array<string>
     */
    protected function getChangedFiles(?string $oldCommitHash): array
    {
        if ($oldCommitHash === null) {
            return [];
        }

        try {
            $workingDir = base_path();
            $diff = Process::path($workingDir)->run("git diff --name-only {$oldCommitHash} HEAD");
            if ($diff->successful()) {
                $output = trim($diff->output());

                return $output ? explode("\n", $output) : [];
            }
        } catch (\Exception $e) {
            // Silently fail and return empty array
        }

        return [];
    }

    /**
     * Check if any of the specified files/patterns were changed.
     *
     * @param  array<string>  $changedFiles
     * @param  array<string>  $patterns
     */
    protected function filesChanged(array $changedFiles, array $patterns): bool
    {
        foreach ($changedFiles as $file) {
            foreach ($patterns as $pattern) {
                // Check exact match
                if ($file === $pattern) {
                    return true;
                }

                // Check if file path starts with pattern (for directories)
                if (str_starts_with($file, $pattern)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get the current git branch.
     */
    protected function getCurrentBranch(?string $workingDir = null): string
    {
        $workingDir = $workingDir ?? base_path();
        $branch = Process::path($workingDir)->run('git rev-parse --abbrev-ref HEAD');
        if ($branch->successful()) {
            return trim($branch->output());
        }

        // Default to main if branch detection fails
        return 'main';
    }

    /**
     * Configure Git to trust the working directory.
     */
    protected function configureGitSafeDirectory(string $workingDir): void
    {
        try {
            if (! is_dir($workingDir.'/.git')) {
                return;
            }

            Process::run("git config --global --add safe.directory {$workingDir} 2>/dev/null || true");
        } catch (\Exception $e) {
            // Silently fail
        }
    }

    /**
     * Clear Laravel caches after update.
     */
    protected function clearCaches(): void
    {
        try {
            $workingDir = base_path();
            $commands = ['config:clear', 'route:clear', 'view:clear', 'cache:clear'];

            foreach ($commands as $command) {
                Process::path($workingDir)->run("php artisan {$command}");
            }
        } catch (\Exception $e) {
            // Silently fail
        }
    }

    /**
     * Ensure Git has proper permissions to modify files.
     */
    protected function ensureGitPermissions(string $workingDir): void
    {
        try {
            $uid = posix_getuid();
            $gid = posix_getgid();

            $chownResult = Process::run("chown -R {$uid}:{$gid} {$workingDir} 2>&1");

            if (! $chownResult->successful()) {
                Process::run("chown -R {$uid}:{$gid} {$workingDir}/.git 2>/dev/null || true");
                Process::run("chown {$uid}:{$gid} {$workingDir} 2>/dev/null || true");
            }
        } catch (\Exception $e) {
            // Silently fail
        }
    }

    /**
     * Fix file permissions after git operations.
     *
     * @param  array<string>  $changedFiles
     */
    protected function fixPermissions(array $changedFiles): void
    {
        try {
            $workingDir = base_path();
            $uid = posix_getuid();
            $gid = posix_getgid();

            foreach ($changedFiles as $file) {
                $fullPath = $workingDir.'/'.$file;
                if (file_exists($fullPath)) {
                    @chown($fullPath, $uid);
                    @chgrp($fullPath, $gid);
                }
            }

            Process::run("chown -R {$uid}:{$gid} {$workingDir}/.git 2>/dev/null || true");
        } catch (\Exception $e) {
            // Silently fail
        }
    }

    /**
     * Check if the application is running in a Docker container.
     */
    protected function isRunningInDocker(): bool
    {
        // Check for .dockerenv file (standard Docker indicator)
        if (file_exists('/.dockerenv')) {
            return true;
        }

        // Check cgroup for Docker indicator
        if (file_exists('/proc/self/cgroup')) {
            $cgroup = file_get_contents('/proc/self/cgroup');
            if ($cgroup && str_contains($cgroup, 'docker')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Run the deploy.sh script for non-Docker deployments.
     *
     * @return array{success: bool, message: string, output: string|null, error: string|null}
     */
    protected function runDeployScript(string $workingDir): array
    {
        $result = [
            'success' => false,
            'message' => '',
            'output' => null,
            'error' => null,
        ];

        $deployScript = $workingDir.'/deploy.sh';

        // Ensure script is executable
        @chmod($deployScript, 0755);

        // Run deploy.sh script
        // Note: deploy.sh will prompt for APP_URL if missing, which is expected behavior
        // In production, APP_URL should already be set, so it won't prompt
        $deploy = Process::path($workingDir)
            ->timeout(600) // 10 minute timeout for deployment
            ->run('bash deploy.sh');

        if ($deploy->successful()) {
            $result['success'] = true;
            $result['message'] = 'Update completed successfully';
            $result['output'] = trim($deploy->output());
        } else {
            $result['error'] = trim($deploy->errorOutput());
            $result['message'] = 'Update failed';
            $result['output'] = trim($deploy->output());
        }

        return $result;
    }
}
