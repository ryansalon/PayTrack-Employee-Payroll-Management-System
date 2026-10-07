<?= $this->extend('layout/main') ?>

<?= $this->section('content') ?>
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-4">Account Settings</h5>
                    
                    <form action="/user/update_password" method="post">
                        <div class="mb-4">
                            <label class="form-label text-muted small fw-bold">CURRENT PASSWORD</label>
                            <input type="password" name="old_password" class="form-control border-light shadow-sm">
                        </div>
                        <hr class="text-muted">
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">NEW PASSWORD</label>
                            <input type="password" name="new_password" class="form-control border-light shadow-sm">
                        </div>
                        <div class="mb-4">
                            <label class="form-label text-muted small fw-bold">CONFIRM NEW PASSWORD</label>
                            <input type="password" name="confirm_password" class="form-control border-light shadow-sm">
                        </div>
                        <button type="submit" class="btn btn-primary px-4 fw-bold">Update Account Security</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3"><i class="fas fa-server text-primary me-2"></i>System Deployment Status</h5>
                    <p class="text-muted small">Configured to operate seamlessly both on municipal local intranet (offline) and online cloud hosting.</p>
                    
                    <div class="list-group list-group-flush border rounded">
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span class="small fw-bold">Operating Environment</span>
                            <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Offline / Online Hybrid Ready</span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span class="small fw-bold">Base System URL</span>
                            <span class="small font-monospace text-muted"><?= base_url() ?></span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span class="small fw-bold">Database Persistence</span>
                            <span class="badge bg-info text-dark">GSIS / Rates Month-to-Month Retained</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3"><i class="fas fa-file-invoice-dollar text-warning me-2"></i>Municipal Billing & Licensing Model</h5>
                    <p class="text-muted small mb-3">Itemized cost structure separating initial hardware installation from recurring monthly software licensing.</p>

                    <div class="row g-2 text-center">
                        <div class="col-6">
                            <div class="p-3 border rounded bg-light">
                                <i class="fas fa-tools fa-2x text-secondary mb-2"></i>
                                <h6 class="fw-bold mb-1">Hardware & Installation</h6>
                                <p class="text-muted x-small mb-1">One-Time Capital Outlay</p>
                                <span class="badge bg-secondary">Separate Upfront Item</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 border rounded bg-light">
                                <i class="fas fa-calendar-check fa-2x text-primary mb-2"></i>
                                <h6 class="fw-bold mb-1">Monthly Subscription</h6>
                                <p class="text-muted x-small mb-1">Recurring Maintenance</p>
                                <span class="badge bg-primary">Reduced Monthly Rate</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>