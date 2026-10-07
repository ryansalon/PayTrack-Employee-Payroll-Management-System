<?= $this->extend('layout/main') ?>

<?= $this->section('content') ?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm p-4">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <div>
                        <h5 class="fw-bold mb-1"><i class="fas fa-user-tag text-warning me-2"></i>Register Job Order (J.O.) Employee</h5>
                        <p class="text-muted small mb-0">Dedicated registration form for non-plantilla / contract of service employees.</p>
                    </div>
                    <span class="badge bg-warning text-dark px-3 py-2 fw-bold">Job Order (J.O.)</span>
                </div>

                <form action="/employee/store" method="post">
                    <input type="hidden" name="employment_status" value="Job Order">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold">J.O. CONTROL / EMPLOYEE ID</label>
                            <input type="text" class="form-control bg-light" value="<?= $generated_id ?>" readonly>
                            <input type="hidden" name="employee_id" value="<?= $generated_id ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold">FULL NAME</label>
                            <input type="text" name="full_name" class="form-control" placeholder="Last Name, First Name M.I." required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold">DESIGNATION / POSITION</label>
                            <input type="text" name="position" class="form-control" placeholder="e.g. Utility Worker / Heavy Equipment Operator / Aide" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold">OFFICE / DEPARTMENT ASSIGNMENT</label>
                            <input type="text" id="office_display" class="form-control" placeholder="Click to select office" readonly required style="cursor:pointer;" data-bs-toggle="modal" data-bs-target="#officeModal">
                            <input type="hidden" name="office_id" id="office_id">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-primary small fw-bold"><i class="fas fa-credit-card me-1"></i>BANK / ATM ACCOUNT NUMBER</label>
                            <input type="text" name="atm_account_no" class="form-control border-primary" placeholder="e.g. 1234-5678-90 (Landbank / LBP)">
                            <div class="form-text small">Required for direct payroll bank deposit.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold">SALARY / DAILY / MONTHLY RATE (₱)</label>
                            <input type="number" step="0.01" name="salary_rate" class="form-control" placeholder="0.00" value="0.00">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold">CONTACT NUMBER (OPTIONAL)</label>
                            <input type="text" name="contact_number" class="form-control" placeholder="09XX-XXX-XXXX">
                        </div>

                        <div class="col-12 mt-4 text-end">
                            <a href="/employee" class="btn btn-light px-4 me-2">Cancel</a>
                            <button type="submit" class="btn btn-warning px-4 fw-bold"><i class="fas fa-save me-1"></i> Save J.O. Employee</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Office Selector Modal -->
<div class="modal fade" id="officeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold">Select Office</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="list-group mb-3" id="officeList">
                    <?php foreach($offices as $office): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center" id="office-row-<?= $office['id'] ?>">
                            <span style="cursor:pointer; flex:1;" onclick="selectOffice('<?= $office['id'] ?>', '<?= esc($office['office_name']) ?>')">
                                <?= esc($office['office_name']) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function selectOffice(id, name) {
        document.getElementById('office_id').value = id;
        document.getElementById('office_display').value = name;
        bootstrap.Modal.getInstance(document.getElementById('officeModal')).hide();
    }
</script>
<?= $this->endSection() ?>
