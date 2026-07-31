<?php
declare(strict_types=1);

require_once __DIR__ . '/automation-rules.php';

/**
 * Recover stale approved HomeServer proposals and deterministically refresh
 * every affected parent execution.
 *
 * The retained recovery statement updates the approval and its action receipt
 * atomically. MySQL and MariaDB may report two changed rows for that joined
 * update, so this wrapper records the candidates before recovery and verifies
 * the durable failure evidence afterward instead of trusting driver row-count
 * semantics. Repeated calls are safe.
 */
function automation_recover_interrupted_approvals_complete(): int
{
    if (!automation_schema_available()) {
        return 0;
    }

    $candidates = db()->query(
        'SELECT approval.id,approval.execution_id,approval.action_receipt_id
         FROM automation_approvals approval
         WHERE approval.status="approved"
           AND approval.resolved_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 10 MINUTE)'
    )->fetchAll();

    if (!$candidates) {
        return 0;
    }

    automation_recover_interrupted_approvals();

    $verification = db()->prepare(
        'SELECT approval.status AS approval_status,
                receipt.status AS receipt_status,
                receipt.error_code
         FROM automation_approvals approval
         JOIN automation_action_receipts receipt
           ON receipt.id=approval.action_receipt_id
         WHERE approval.id=:approval_id
           AND approval.action_receipt_id=:receipt_id
         LIMIT 1'
    );

    $recovered = 0;
    foreach ($candidates as $candidate) {
        $verification->execute([
            'approval_id' => (int)$candidate['id'],
            'receipt_id' => (int)$candidate['action_receipt_id'],
        ]);
        $evidence = $verification->fetch();

        if (
            !$evidence
            || (string)$evidence['approval_status'] !== 'failed'
            || (string)$evidence['receipt_status'] !== 'failed'
            || (string)$evidence['error_code'] !== 'approval_worker_interrupted'
        ) {
            continue;
        }

        automation_refresh_execution_status((int)$candidate['execution_id']);
        $recovered++;
    }

    return $recovered;
}
