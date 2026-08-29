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
}
