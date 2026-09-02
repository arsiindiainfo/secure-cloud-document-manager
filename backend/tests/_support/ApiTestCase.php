<?php

namespace Tests\Support;

use App\Database\Seeds\DemoSeeder;
use App\Libraries\JwtService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

/**
 * Base for every controller feature test (§25): runs the REAL migrations
 * (all 10 tables + 13 stored procedures) against the `tests` DB group and
 * seeds the same three role accounts DemoSeeder creates for local dev — the
 * plan is explicit that these hit real stored procedures, never mocks.
 *
 * Migration is expensive here (13 CREATE PROCEDURE statements), so unlike
 * CI4's usual $refresh=true default, the schema is built once per test
 * class (migrateOnce) and each test starts from a clean slate via a plain
 * TRUNCATE + reseed instead of a full drop/recreate — same isolation
 * guarantee, far cheaper.
 */
abstract class ApiTestCase extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace   = null; // migrate every namespace, i.e. App's real migrations
    protected $migrateOnce = true;
    protected $refresh     = false;
    protected $seedOnce    = false; // reseed every test, right after truncateAppTables()
    protected $seed        = DemoSeeder::class;

    private const APP_TABLES = [
        'audit_logs', 'document_permissions', 'document_processing_jobs',
        'document_versions', 'documents', 'folders', 'refresh_tokens',
        'share_links', 'trash_retention_settings', 'users',
    ];

    protected function setUpDatabase()
    {
        $this->loadDependencies();
        $this->setUpMigrate();
        $this->truncateAppTables();
        $this->setUpSeed();
        // RateLimitFilter (§5/§15) is cache-backed, not DB-backed — without
        // this, every test hitting a rate-limited route would share one
        // counter across the whole suite (and across separate runs, since
        // the file cache persists on disk), tripping RATE_LIMITED at an
        // unrelated test purely based on run order.
        Services::cache()->clean();
    }

    private function truncateAppTables(): void
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');
        foreach (self::APP_TABLES as $table) {
            $this->db->query("TRUNCATE TABLE {$table}");
        }
        $this->db->query('SET FOREIGN_KEY_CHECKS = 1');
    }

    protected function bearerFor(int $userId, string $role): array
    {
        return ['Authorization' => 'Bearer ' . (new JwtService())->issueAccessToken($userId, $role)];
    }

    protected function adminHeaders(): array
    {
        return $this->bearerFor(1, 'ADMIN');
    }

    protected function managerHeaders(): array
    {
        return $this->bearerFor(2, 'MANAGER');
    }

    protected function employeeHeaders(): array
    {
        return $this->bearerFor(3, 'EMPLOYEE');
    }
}
