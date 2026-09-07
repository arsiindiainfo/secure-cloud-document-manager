<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Commands;

use App\Libraries\EmailTemplate;
use App\Libraries\S3Service;
use App\Libraries\SesMailer;
use App\Models\DocumentModel;
use App\Models\DocumentVersionModel;
use App\Models\UserModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

/**
 * `php spark cleanup:old-files` — meant to run on a schedule (there's no
 * CI4 Task Scheduler configured in this app; the EC2 crontab calls this
 * directly, see docs/backend-setup.md). Per user: their 5 most recently
 * uploaded (non-deleted) documents are always kept; anything beyond that
 * top 5 which is also older than 30 days gets its S3 objects (every
 * version + thumbnail) deleted and its DB rows permanently removed —
 * reusing the same soft-delete-then-hard-delete pair Trash's "delete
 * forever" already relies on, attributed to the file's own owner since a
 * cron run has no real acting admin to attribute it to. Each affected
 * user gets a summary email afterward — this is the only place that
 * outcome is surfaced; there's no in-app notification center.
 */
class CleanupOldFilesCommand extends BaseCommand
{
    protected $group       = 'Database';
    protected $name        = 'cleanup:old-files';
    protected $description = "Keeps each user's latest 5 files; purges anything older than 30 days beyond that (S3 + DB), then emails a summary.";

    private const RETENTION_DAYS  = 30;
    private const KEEP_LATEST     = 5;

    public function run(array $params): void
    {
        $db        = db_connect();
        $documents = new DocumentModel();
        $versions  = new DocumentVersionModel();
        $users     = new UserModel();
        $s3        = new S3Service();

        $ownerRows = $db->query('SELECT DISTINCT created_by FROM documents WHERE deleted_at IS NULL')->getResultArray();

        $totalPurged = 0;

        foreach ($ownerRows as $ownerRow) {
            $userId = (int) $ownerRow['created_by'];

            $docs = $db->query(<<<'SQL'
                SELECT id, name, created_at FROM documents
                WHERE created_by = ? AND deleted_at IS NULL
                ORDER BY created_at DESC
            SQL, [$userId])->getResultArray();

            $candidates = array_slice($docs, self::KEEP_LATEST);
            $purgedNames = [];

            foreach ($candidates as $doc) {
                if (strtotime($doc['created_at']) > strtotime('-' . self::RETENTION_DAYS . ' days')) {
                    continue; // beyond the top 5, but not old enough yet
                }

                $documentId = (int) $doc['id'];

                $keys = [];
                foreach ($versions->allForDocument($documentId) as $version) {
                    $keys[] = $version->s3_key;
                    if ($version->thumbnail_s3_key !== null) {
                        $keys[] = $version->thumbnail_s3_key;
                    }
                }
                if ($keys !== []) {
                    $s3->deleteObjects($keys);
                }

                $documents->softDelete($documentId, $userId);
                $documents->hardDelete($documentId, $userId);

                $purgedNames[] = $doc['name'];
                $totalPurged++;
            }

            if ($purgedNames !== []) {
                $this->notifyUser($users, $userId, $purgedNames);
                CLI::write("User #{$userId}: purged " . count($purgedNames) . ' file(s) — ' . implode(', ', $purgedNames), 'yellow');
            }
        }

        CLI::write("Done — {$totalPurged} file(s) purged total.", 'green');
    }

    /** @param list<string> $fileNames */
    private function notifyUser(UserModel $users, int $userId, array $fileNames): void
    {
        $user = $users->find($userId);
        if ($user === null) {
            return;
        }

        $items = implode('', array_map(
            static fn (string $name): string => '<li>' . esc($name, 'html') . '</li>',
            $fileNames,
        ));
        $body = '<p>Hi ' . esc($user->name, 'html') . ',</p>'
            . '<p>Our 30-day retention policy keeps only your 5 most recently uploaded files once they\'re older than 30 days. '
            . 'The following older files were just permanently removed from the server:</p>'
            . "<ul style=\"margin: 12px 0; padding-left: 20px;\">{$items}</ul>"
            . '<p style="color: #94a3b8;">This cannot be undone — re-upload if you still need any of them.</p>';

        $html = EmailTemplate::render('Files removed (30-day retention policy)', $body);

        try {
            if (! (new SesMailer())->send($user->email, 'Some of your files were removed — Secure Cloud Document Manager', $html)) {
                log_message('error', 'Failed to send retention-cleanup email to ' . $user->email);
            }
        } catch (Throwable $e) {
            log_message('error', 'Failed to send retention-cleanup email: ' . $e->getMessage());
        }
    }
}
