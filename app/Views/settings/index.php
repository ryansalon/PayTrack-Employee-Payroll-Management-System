<?= $this->extend('layout/main') ?>

<?= $this->section('content') ?>
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-dark text-white py-3">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-landmark me-2"></i>LGU &amp; Payroll Signatory Settings</h5>
                </div>
                <div class="card-body p-4">
                    <p class="text-muted small mb-4">
                        These names and titles appear on printed Municipal Payroll sheets, Excel exports, and individual payslips.
                    </p>
                    <form action="/settings/update" method="post">
                        <?= csrf_field() ?>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-bold">LGU NAME</label>
                                <input type="text" name="lgu_name" class="form-control" value="<?= esc(old('lgu_name', $settings['lgu_name'])) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-bold">MUNICIPALITY / PROVINCE</label>
                                <input type="text" name="municipality_label" class="form-control" value="<?= esc(old('municipality_label', $settings['municipality_label'])) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-bold">MAYOR / APPROVING OFFICER NAME</label>
                                <input type="text" name="mayor_name" class="form-control" value="<?= esc(old('mayor_name', $settings['mayor_name'])) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-bold">MAYOR / APPROVING OFFICER TITLE</label>
                                <input type="text" name="mayor_title" class="form-control" value="<?= esc(old('mayor_title', $settings['mayor_title'])) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-bold">TREASURER / DISBURSING OFFICER NAME</label>
                                <input type="text" name="treasurer_name" class="form-control" value="<?= esc(old('treasurer_name', $settings['treasurer_name'])) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-bold">TREASURER / DISBURSING OFFICER TITLE</label>
                                <input type="text" name="treasurer_title" class="form-control" value="<?= esc(old('treasurer_title', $settings['treasurer_title'])) ?>" required>
                            </div>
                            <div class="col-12 mt-4 text-end">
                                <button type="submit" class="btn btn-primary px-4 fw-bold">
                                    <i class="fas fa-save me-1"></i> Save Settings
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
