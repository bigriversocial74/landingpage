<?php
declare(strict_types=1);

trait Vp3UpdateInstallTrait
{
    public function install(int $releaseId, ?int $userId = null, string $actorType = 'administrator'): array
    {
        $release = $this->repository->release($releaseId);
        $prepared = $this->repository->preparedJobForRelease($releaseId);
        if (!$release || !$prepared) {
            throw new RuntimeException('Download and verify this release before installing it.');
        }
        $manifest = json_decode((string)$release['manifest_json'], true);
        if (!is_array($manifest)) {
            throw new RuntimeException('The stored VP3 release manifest is invalid.');
        }
        $manifest = Vp3UpdateManifest::verify($manifest);
        $stagePath = NMM_ROOT . '/' . ltrim((string)$prepared['staging_path'], '/');
        if (!is_dir($stagePath)) {
            throw new RuntimeException('The verified update staging directory is missing.');
        }
        $finalEligibility = vp3_update_eligibility([
            'channel' => (string)$manifest['channel'],
            'version' => (string)$manifest['version'],
            'critical_security' => (bool)$manifest['critical_security'],
            'recovery_update' => (bool)$manifest['recovery_update'],
            'required_storage_bytes' => (int)$manifest['required_storage_bytes'],
            'manifest_signed' => true,
            'checksum_valid' => true,
            'package_signature_valid' => true,
            'migration_compatible' => true,
        ]);
        if (empty($finalEligibility['eligible'])) {
            throw new RuntimeException('The VP3 license does not authorize this managed update: ' .
                implode(', ', $finalEligibility['reasons'] ?? []) . '.');
        }

        $job = $this->repository->createJob('install', $releaseId, $userId, $actorType);
        $jobId = (int)$job['id'];
        $previousVersion = vp3_update_installed_version();
        $targetVersion = (string)$manifest['version'];
        $backup = null;
        $maintenanceToken = Vp3Crypto::base64UrlEncode(random_bytes(32));
        try {
            $this->repository->updateJob($jobId, 'backing_up', 'Creating pre-update file and database backup', 10, [
                'previous_version' => $previousVersion,
                'target_version' => $targetVersion,
                'package_path' => (string)$prepared['package_path'],
                'staging_path' => (string)$prepared['staging_path'],
            ]);
            $backup = $this->backup->create($jobId, $previousVersion, $targetVersion);
            $this->repository->updateJob($jobId, 'backing_up', 'Pre-update backup verified', 25, [
                'backup_id' => (int)$backup['id'],
            ]);
            $eligibility = vp3_update_eligibility([
                'channel' => (string)$manifest['channel'],
                'version' => $targetVersion,
                'critical_security' => (bool)$manifest['critical_security'],
                'recovery_update' => (bool)$manifest['recovery_update'],
                'required_storage_bytes' => (int)$manifest['required_storage_bytes'],
                'manifest_signed' => true,
                'checksum_valid' => true,
                'package_signature_valid' => true,
                'migration_compatible' => true,
                'backup_completed' => true,
            ]);
            if (empty($eligibility['eligible'])) {
                throw new RuntimeException('Final update eligibility failed: ' . implode(', ', $eligibility['reasons'] ?? []) . '.');
            }

            $this->writeMaintenanceFlag($jobId, $targetVersion, $maintenanceToken);
            $this->repository->updateJob($jobId, 'installing', 'Activating staged application files', 40);
            $fileResults = $this->installFiles($stagePath, $manifest);
            $this->repository->updateJob($jobId, 'migrating', 'Applying signed database migrations', 68, [
                'installed_files_json' => json_encode($fileResults['installed'], JSON_UNESCAPED_SLASHES),
                'created_files_json' => json_encode($fileResults['created'], JSON_UNESCAPED_SLASHES),
            ]);
            $migrationResults = $this->runMigrations($jobId, $releaseId, $stagePath, $manifest);
            vp3_update_store_installed_version($targetVersion);
            $this->repository->updateJob($jobId, 'health_check', 'Running local and remote health checks', 88, [
                'migration_results_json' => json_encode($migrationResults, JSON_UNESCAPED_SLASHES),
            ]);
            $local = $this->health->local($targetVersion);
            $remote = $this->health->remote($targetVersion, $maintenanceToken);
            $health = ['local' => $local, 'remote' => $remote];
            if (empty($local['ok']) || empty($remote['ok'])) {
                throw new RuntimeException('Post-update health validation failed.');
            }
            $this->repository->receipt(
                'health_check',
                'success',
                'activation_health_passed',
                'The updated POD passed local and new-process HTTP health checks.',
                $health,
                $jobId,
                $releaseId
            );
            $this->clearMaintenanceFlag();
            $this->repository->updateJob($jobId, 'completed', 'Update installed successfully', 100, [
                'health_results_json' => json_encode($health, JSON_UNESCAPED_SLASHES),
            ]);
            $this->cleanup();
            log_activity('vp3_managed_update_completed', 'vp3_update_job', $jobId, [
                'from' => $previousVersion,
                'to' => $targetVersion,
            ]);
            return [
                'job' => $this->repository->job($jobId),
                'backup' => $backup,
                'health' => $health,
            ];
        } catch (Throwable $exception) {
            $rollbackError = null;
            try {
                if (is_array($backup)) {
                    $this->repository->updateJob($jobId, 'rolling_back', 'Restoring pre-update backup', 92);
                    $this->backup->restore($backup, $this->repository->job($jobId) ?? []);
                    $this->repository->receipt(
                        'rollback',
                        'success',
                        'automatic_rollback_completed',
                        'The POD was restored to the pre-update version.',
                        ['source_version' => $previousVersion],
                        $jobId,
                        $releaseId
                    );
                    $this->repository->updateJob($jobId, 'rolled_back', 'Automatic rollback completed', 100, [
                        'error_code' => 'update_activation_failed',
                        'error_message' => mb_substr($exception->getMessage(), 0, 2000),
                    ]);
                } else {
                    $this->failJob($jobId, 'update_install_failed', $exception);
                }
            } catch (Throwable $rollbackException) {
                $rollbackError = $rollbackException;
                $this->repository->updateJob($jobId, 'failed', 'Update and rollback failed', 100, [
                    'error_code' => 'update_and_rollback_failed',
                    'error_message' => mb_substr(
                        $exception->getMessage() . ' Rollback error: ' . $rollbackException->getMessage(),
                        0,
                        2000
                    ),
                ]);
            } finally {
                $this->clearMaintenanceFlag();
            }
            if ($rollbackError) {
                throw new RuntimeException(
                    'The update failed and automatic rollback also failed: ' . $rollbackError->getMessage(),
                    0,
                    $exception
                );
            }
            throw new RuntimeException(
                'The update failed and the POD was rolled back: ' . $exception->getMessage(),
                0,
                $exception
            );
        }
    }

}
