<?php
declare(strict_types=1);

final class Vp3UpdateAgent
{
    use Vp3UpdateCheckPrepareTrait;
    use Vp3UpdateInstallTrait;
    use Vp3UpdateRollbackScheduleTrait;
    use Vp3UpdateOperationsTrait;

    private Vp3UpdateRepository $repository;
    private Vp3UpdateArchive $archive;
    private Vp3UpdateBackup $backup;
    private Vp3UpdateHealth $health;

    public function __construct()
    {
        if (!vp3_update_schema_available()) {
            throw new RuntimeException('Import database/vp3_pod_managed_updates_v65.sql before using managed updates.');
        }
        $this->repository = new Vp3UpdateRepository();
        $this->archive = new Vp3UpdateArchive();
        $this->backup = new Vp3UpdateBackup($this->repository);
        $this->health = new Vp3UpdateHealth();
    }
}
