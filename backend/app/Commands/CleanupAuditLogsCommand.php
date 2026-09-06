<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * `php spark cleanup:audit-logs` — meant to run on a schedule (see the EC2
 * crontab entry in docs/backend-setup.md; there's no CI4 Task Scheduler
 * configured in this app). Keeps the last 3 months of audit history and
 * nothing older.
 */
class CleanupAuditLogsCommand extends BaseCommand
{
    protected $group       = 'Database';
    protected $name        = 'cleanup:audit-logs';
    protected $description = 'Deletes audit_logs rows older than 3 months.';

    public function run(array $params): void
    {
        $db = db_connect();
        $db->query('DELETE FROM audit_logs WHERE created_at < NOW() - INTERVAL 3 MONTH');

        CLI::write('Deleted ' . $db->affectedRows() . ' audit log row(s) older than 3 months.', 'green');
    }
}
