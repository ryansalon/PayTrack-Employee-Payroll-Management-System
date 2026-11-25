<?= $this->extend('layout/main') ?>

<?= $this->section('content') ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1"><i class="fas fa-database me-2 text-primary"></i>Database Backup &amp; Restore</h4>
            <p class="text-muted small mb-0">
                Save a copy to a USB flash drive after every payday so payroll history is safe even if this PC fails.
            </p>
        </div>
        <span class="badge <?= !empty($last_backup_at) ? 'bg-success' : 'bg-warning text-dark' ?> px-3 py-2">
            Last Backup: <?= !empty($last_backup_at) ? esc(date('M d, Y g:i A', strtotime($last_backup_at))) : 'Never' ?>
        </span>
    </div>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-2"><i class="fas fa-download text-success me-2"></i>Download Full Backup</h5>
                    <p class="text-muted small mb-4">
                        Exports all users, offices, employees, deductions, payroll records, period locks, and LGU settings as a portable <code>.sql</code> file and also keeps a rotating local copy under <code>writable/backups/</code>.
                    </p>
                    <form action="/backup/download" method="post">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-success px-4 fw-bold">
                            <i class="fas fa-file-download me-1"></i> Download Backup (.sql)
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100 border-danger">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-2 text-danger"><i class="fas fa-upload me-2"></i>Restore from Backup</h5>
                    <p class="text-muted small mb-3">
                        Replaces the current database with an uploaded PayTrack <code>.sql</code> backup. A pre-restore safety backup is automatically saved first.
                    </p>
                    <form action="/backup/restore" method="post" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">BACKUP FILE (.sql)</label>
                            <input type="file" name="backup_file" accept=".sql" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Type <span class="text-danger">RESTORE</span> to confirm:</label>
                            <input type="text" name="confirm" id="restoreConfirmInput" class="form-control" autocomplete="off" required>
                        </div>
                        <button type="submit" class="btn btn-danger px-4 fw-bold" id="restoreBtn" disabled>
                            <i class="fas fa-history me-1"></i> Restore Database
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3">Recent Local Backup Copies (<code>writable/backups/</code>)</h6>
                    <?php if (empty($local_backups)): ?>
                        <p class="text-muted small mb-0">No local backup files yet. Click "Download Backup" above to create your first copy.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light small">
                                    <tr>
                                        <th>FILE NAME</th>
                                        <th>SIZE</th>
                                        <th>CREATED</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($local_backups as $b): ?>
                                    <tr>
                                        <td class="font-monospace small"><?= esc($b['name']) ?></td>
                                        <td class="small"><?= number_format($b['size'] / 1024, 1) ?> KB</td>
                                        <td class="small text-muted"><?= esc($b['modified']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
document.getElementById('restoreConfirmInput').addEventListener('input', function () {
    document.getElementById('restoreBtn').disabled = (this.value !== 'RESTORE');
});
</script>
<?= $this->endSection() ?>
