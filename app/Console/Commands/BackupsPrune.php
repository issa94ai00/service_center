<?php

namespace App\Console\Commands;

use App\Backups\BackupService;
use Illuminate\Console\Command;

class BackupsPrune extends Command
{
    protected $signature = 'backups:prune';

    protected $description = 'Delete backups past their retention (config/backups.php)';

    public function handle(BackupService $backups): int
    {
        $this->info($backups->prune().' backup(s) deleted.');

        return self::SUCCESS;
    }
}
