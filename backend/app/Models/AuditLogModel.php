<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Most audit rows are written inside the stored procedure that performs the
 * action itself, in the same transaction (§11) — see e.g. sp_folder_create.
 * This model only covers the handful of audit events that have no
 * corresponding SP write: login success/failure, download-URL issuance,
 * and ADMIN ACL-bypass logging (§6.3, §18).
 */
class AuditLogModel extends Model
{
    protected $table         = 'audit_logs';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['user_id', 'action', 'entity_type', 'entity_id', 'details', 'ip_address'];
    protected $useTimestamps = false;

    /** @param array<string, mixed> $details */
    public function record(?int $userId, string $action, string $entityType, int $entityId, array $details = [], ?string $ip = null): void
    {
        $this->insert([
            'user_id'     => $userId,
            'action'      => $action,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'details'     => json_encode($details),
            'ip_address'  => $ip,
        ]);
    }

    /**
     * §19 — GET /audit-logs, ADMIN only. Filters are all optional and
     * combine with AND; date bounds are inclusive.
     *
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function paginatedList(
        ?string $entityType,
        ?int $entityId,
        ?int $userId,
        ?string $action,
        ?string $dateFrom,
        ?string $dateTo,
        int $page,
        int $limit,
    ): array {
        // Two independent builder instances (not the cached Model::builder())
        // so counting can't reset the conditions the row fetch still needs.
        $apply = static function ($builder) use ($entityType, $entityId, $userId, $action, $dateFrom, $dateTo) {
            if ($entityType !== null) {
                $builder->where('entity_type', $entityType);
            }
            if ($entityId !== null) {
                $builder->where('entity_id', $entityId);
            }
            if ($userId !== null) {
                $builder->where('user_id', $userId);
            }
            if ($action !== null) {
                $builder->where('action', $action);
            }
            if ($dateFrom !== null) {
                $builder->where('created_at >=', $dateFrom);
            }
            if ($dateTo !== null) {
                $builder->where('created_at <=', $dateTo);
            }

            return $builder;
        };

        $total = $apply($this->db->table($this->table))->countAllResults();
        $rows  = $apply($this->db->table($this->table))
            ->orderBy('created_at', 'desc')
            ->limit($limit, ($page - 1) * $limit)
            ->get()->getResultArray();

        $items = array_map(static fn (array $row): array => [
            'id'         => (int) $row['id'],
            'userId'     => $row['user_id'] !== null ? (int) $row['user_id'] : null,
            'action'     => $row['action'],
            'entityType' => $row['entity_type'],
            'entityId'   => (int) $row['entity_id'],
            'details'    => $row['details'] !== null ? json_decode($row['details'], true) : null,
            'createdAt'  => $row['created_at'],
        ], $rows);

        return ['items' => $items, 'total' => $total];
    }
}
