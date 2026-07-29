<?php
declare(strict_types=1);

trait Vp3UpdateCheckPrepareTrait
{
    public function check(?int $userId = null, string $actorType = 'administrator'): array
    {
        $job = $this->repository->createJob('check', null, $userId, $actorType);
        $jobId = (int)$job['id'];
        $this->repository->updateJob($jobId, 'checking', 'Requesting signed VP3 release manifest', 10, [
            'previous_version' => vp3_update_installed_version(),
        ]);
        try {
            $provider = (new Vp3UpdateProviderClient())->check();
            $manifest = Vp3UpdateManifest::verify((array)$provider['manifest']);
            $eligibilityInput = [
                'channel' => (string)$manifest['channel'],
                'version' => (string)$manifest['version'],
                'critical_security' => (bool)$manifest['critical_security'],
                'recovery_update' => (bool)$manifest['recovery_update'],
                'required_storage_bytes' => (int)$manifest['required_storage_bytes'],
                'manifest_signed' => true,
            ];
            $eligibility = vp3_update_eligibility($eligibilityInput);
            $policy = vp3_managed_updates_policy();
            if (empty($policy['automatic_updates_enabled']) && empty($manifest['critical_security']) && empty($manifest['recovery_update'])) {
                $eligibility['eligible'] = false;
                $eligibility['reasons'] = array_values(array_unique(array_merge(
                    $eligibility['reasons'] ?? [],
                    ['vp3_license_required_for_managed_updates']
                )));
            }
            $release = $this->repository->storeRelease($manifest, $eligibility);
            $this->repository->receipt(
                'manifest_verify',
                'success',
                'signed_manifest_verified',
                'The VP3 release manifest and package descriptor were verified.',
                [
                    'version' => $manifest['version'],
                    'channel' => $manifest['channel'],
                    'newer_version' => Vp3UpdateManifest::hasNewerVersion($manifest),
                    'request_id' => $provider['request_id'],
                    'latency_ms' => $provider['latency_ms'],
                ],
                $jobId,
                (int)$release['id']
            );
            $this->repository->updateJob($jobId, 'completed', 'Update check complete', 100, [
                'target_version' => (string)$manifest['version'],
            ]);
            return [
                'job' => $this->repository->job($jobId),
                'release' => $release,
                'manifest' => $manifest,
                'eligibility' => $eligibility,
                'newer_version' => Vp3UpdateManifest::hasNewerVersion($manifest),
            ];
        } catch (Throwable $exception) {
            $this->failJob($jobId, 'update_check_failed', $exception);
            throw $exception;
        }
    }

    public function prepare(int $releaseId, ?int $userId = null, string $actorType = 'administrator'): array
    {
        $release = $this->repository->release($releaseId);
        if (!$release) {
            throw new RuntimeException('The selected VP3 release was not found.');
        }
        $manifest = json_decode((string)$release['manifest_json'], true);
        if (!is_array($manifest)) {
            throw new RuntimeException('The stored VP3 release manifest is invalid.');
        }
        $manifest = Vp3UpdateManifest::verify($manifest);
        if (!Vp3UpdateManifest::hasNewerVersion($manifest)) {
            throw new RuntimeException('The selected release is not newer than the installed POD version.');
        }
        $job = $this->repository->createJob('prepare', $releaseId, $userId, $actorType);
        $jobId = (int)$job['id'];
        $root = vp3_update_work_root();
        $package = (array)$manifest['package'];
        $archivePath = $root . '/downloads/' . preg_replace('/[^A-Za-z0-9._-]/', '-', (string)$manifest['release_id']) . '.zip';
        $stagePath = $root . '/staging/' . (string)$job['job_uuid'];
        try {
            $this->repository->updateJob($jobId, 'downloading', 'Downloading signed update package', 15, [
                'previous_version' => vp3_update_installed_version(),
                'target_version' => (string)$manifest['version'],
                'package_path' => vp3_update_relative_path($archivePath),
                'staging_path' => vp3_update_relative_path($stagePath),
            ]);
            $providerHost = strtolower((string)(parse_url((string)vp3_update_settings()['manifest_endpoint'], PHP_URL_HOST) ?: ''));
            $packageHost = strtolower((string)(parse_url((string)$package['url'], PHP_URL_HOST) ?: ''));
            $download = Vp3UpdateHttp::download(
                (string)$package['url'],
                $archivePath,
                max(60, (int)vp3_update_settings()['request_timeout_seconds'] * 5),
                (int)vp3_update_settings()['max_package_bytes'],
                array_values(array_unique(array_filter([$providerHost, $packageHost])))
            );
            if (
                !hash_equals(strtolower((string)$package['sha256']), strtolower((string)$download['sha256']))
                || (int)$download['bytes'] !== (int)$package['size_bytes']
            ) {
                @unlink($archivePath);
                throw new RuntimeException('The downloaded update package failed its checksum or size check.');
            }
            $this->repository->receipt(
                'package_download',
                'success',
                'package_downloaded',
                'The signed update package was downloaded.',
                ['bytes' => $download['bytes'], 'sha256' => $download['sha256']],
                $jobId,
                $releaseId
            );
            $this->repository->updateJob($jobId, 'staging', 'Extracting package into protected staging', 55);
            $staged = $this->archive->extract($manifest, $archivePath, $stagePath);
            $this->repository->receipt(
                'stage',
                'success',
                'package_staged',
                'The package passed archive safety checks and was staged.',
                ['file_count' => $staged['file_count'], 'extracted_bytes' => $staged['extracted_bytes']],
                $jobId,
                $releaseId
            );
            $this->repository->updateJob($jobId, 'completed', 'Package ready for installation', 100);
            return [
                'job' => $this->repository->job($jobId),
                'release' => $release,
                'manifest' => $manifest,
                'staged' => $staged,
            ];
        } catch (Throwable $exception) {
            $this->failJob($jobId, 'update_prepare_failed', $exception);
            throw $exception;
        }
    }

}
