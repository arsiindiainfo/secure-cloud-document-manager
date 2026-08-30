<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

/**
 * `php spark procedures:reload [group]` — re-applies every
 * app/Database/Procedures/*.sql file's DROP+CREATE against a database
 * group, without needing a new migration entry. Migrations only track
 * "has this file run before", so editing an already-applied procedure's
 * SQL (a normal thing to do while iterating on business logic) needs this
 * instead of `php spark migrate`.
 */
class ReloadProceduresCommand extends BaseCommand
{
    protected $group       = 'Database';
    protected $name        = 'procedures:reload';
    protected $description = 'Re-applies every stored procedure in app/Database/Procedures/ against a DB group.';
    protected $usage       = 'procedures:reload [group]';
    protected $arguments   = ['group' => 'DB connection group to target (default: default)'];

    public function run(array $params): void
    {
        $group = $params[0] ?? 'default';
        $db    = Database::connect($group);

        $files = glob(APPPATH . 'Database/Procedures/*.sql') ?: [];
        sort($files);

        foreach ($files as $path) {
            $name = pathinfo($path, PATHINFO_FILENAME);
            $db->query("DROP PROCEDURE IF EXISTS `{$name}`");
            $db->query(file_get_contents($path));
            CLI::write("Reloaded {$name}", 'green');
        }

        CLI::write('Done — ' . count($files) . " procedures reloaded on '{$group}'.", 'yellow');
    }
}
