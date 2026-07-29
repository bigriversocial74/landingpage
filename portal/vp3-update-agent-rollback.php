<?php
declare(strict_types=1);

trait Vp3UpdateRollbackScheduleTrait
{
    public function rollback(int $backupId, ?int $userId = null, string $actorType = 'administrator'): array
    {
        $backup = $this->backup->backup($backupId);
        if ((string)$backup['status'] !== 'ready') {
            throw new RuntimeException('Only a ready update backup can be restored manually.');
        }
        $job = $this->repository->createJob('rollback', null, $userId, $actorType);
        $jobId = (int)$job['id'];
        $token = Vp3Crypto::base64UrlEncode(random_bytes(32));
        $this->repository->updateJob($jobId, 'rolling_back', 'Restoring selected update backup', 20, [
            'backup_id' => $backupId,
            'previous_version' => vp3_update_installed_version(),
            'target_version' => (string)$backup['source_version'],
        ]);
        try {
            $this->writeMaintenanceFlag($jobId, (string)$backup['source_version'], $token);
            $this->backup->restore($backup, $this->repository->job($jobId) ?? []);
            $local = $this->health->local((string)$backup['source_version']);
            $remote = $this->health->remote((string)$backup['source_version'], $token);
            if (empty($local['ok']) || empty($remote['ok'])) {
                throw new RuntimeException('The restored POD did not pass health validation.');
            }
            $this->clearMaintenanceFlag();
            $this->repository->updateJob($jobId, 'rolled_back', 'Manual rollback completed', 100, [
                'health_results_json' => json_encode(['local' => $local, 'remote' => $remote], JSON_UNESCAPED_SLASHES),
            ]);
            return ['job' => $this->repository->job($jobId), 'backup' => $backup];
        } catch (Throwable $exception) {
            $this->clearMaintenanceFlag();
            $this->failJob($jobId, 'manual_rollback_failed', $exception);
            throw $exception;
        }
    }

    public function runScheduled(): array
    {
        $settings = vp3_update_settings();
        if (empty($settings['automatic_check_enabled'])) {
            return ['ok' => true, 'state' => 'automatic_checks_disabled'];
        }
        $checked = $this->check(null, 'worker');
        if (empty($checked['newer_version']) || empty($checked['eligibility']['eligible'])) {
            return ['ok' => true, 'state' => 'checked_no_install', 'check' => $checked];
        }
        if (empty($settings['automatic_install_enabled'])) {
            return ['ok' => true, 'state' => 'approval_required', 'check' => $checked];
        }
        if (!empty($settings['security_only']) && !in_array(
            (string)$checked['manifest']['release_type'],
            ['security','critical'],
            true
        )) {
            return ['ok' => true, 'state' => 'security_only_policy', 'check' => $checked];
        }
        $prepared = $this->prepare((int)$checked['release']['id'], null, 'worker');
        $installed = $this->install((int)$checked['release']['id'], null, 'worker');
        return ['ok' => true, 'state' => 'installed', 'check' => $checked, 'prepare' => $prepared, 'install' => $installed];
    }

}
