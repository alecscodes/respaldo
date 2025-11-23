<?php

namespace App\Console\Commands;

use App\Services\GitUpdateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GitUpdateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'git:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform a git update by pulling latest changes';

    /**
     * Execute the console command.
     */
    public function handle(GitUpdateService $updateService): int
    {
        $this->info('Performing git update...');

        $result = $updateService->performUpdate();

        if ($result['success']) {
            $updated = $result['updated'] ?? false;
            Log::channel('database')->info(
                $updated ? 'Git update performed' : 'Git update check - up to date',
                [
                    'category' => 'system',
                    'message' => $result['message'],
                    'action' => $updated ? 'update' : 'check',
                ]
            );

            $this->info('✓ '.$result['message']);

            if ($result['output']) {
                $this->newLine();
                $this->line($result['output']);
            }

            return Command::SUCCESS;
        }

        Log::channel('database')->error('Git update failed', [
            'category' => 'system',
            'message' => $result['message'],
            'error' => $result['error'] ?? null,
        ]);

        $this->error('✗ '.$result['message']);

        if ($result['error']) {
            $this->error('Error: '.$result['error']);
        }

        if ($result['output']) {
            $this->newLine();
            $this->line($result['output']);
        }

        return Command::FAILURE;
    }
}
