<?= $this->extend('layout/main') ?>

<?= $this->section('content') ?>
<div class="container-fluid">
    <h4 class="fw-bold mb-4">Reports Center</h4>

    <div class="row g-4">
        <!-- Report Generator Form -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3">Generate Summary Report</h6>
                    <form action="/report/generate" method="get">
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">REPORT TYPE</label>
                            <select name="report_type" id="report_type_select" class="form-select" onchange="togglePeriodInputs()">
                                <option value="period">Monthly Payroll Record</option>
                                <option value="quarterly">Quarterly Report (3 Months)</option>
                                <option value="annual">Annual Report (12 Months)</option>
                                <option value="office">Office-wise Summary</option>
                                <option value="deductions">Deduction Analysis</option>
                            </select>
                        </div>
                        <div class="mb-3" id="month_input_group">
                            <label class="form-label text-muted small fw-bold">PAYROLL PERIOD</label>
                            <input type="month" name="period" class="form-control" value="<?= date('Y-m') ?>">
                        </div>
                        <div class="mb-3 d-none" id="quarter_input_group">
                            <label class="form-label text-muted small fw-bold">SELECT QUARTER</label>
                            <select name="quarter" class="form-select">
                                <option value="Q1">1st Quarter (Jan - Mar)</option>
                                <option value="Q2">2nd Quarter (Apr - Jun)</option>
                                <option value="Q3">3rd Quarter (Jul - Sep)</option>
                                <option value="Q4">4th Quarter (Oct - Dec)</option>
                            </select>
                        </div>
                        <div class="mb-3 d-none" id="year_input_group">
                            <label class="form-label text-muted small fw-bold">SELECT YEAR</label>
                            <select name="year" class="form-select">
                                <?php $cy = date('Y'); for($y = $cy; $y >= $cy - 5; $y--): ?>
                                    <option value="<?= $y ?>"><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="form-label text-muted small fw-bold">OFFICE UNIT</label>
                            <select name="office_id" class="form-select">
                                <option value="all">All Offices</option>
                                <?php foreach($offices as $office): ?>
                                    <option value="<?= $office['id'] ?>"><?= esc($office['office_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">
                            <i class="fas fa-sync me-2"></i> Generate Report
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Quick Stats for Context -->
        <div class="col-md-8">
            <div class="row g-3">
                <div class="col-6">
                    <div class="card border-0 shadow-sm p-4 text-center">
                        <i class="fas fa-users fa-2x text-primary mb-2"></i>
                        <p class="text-muted small mb-0">Employees Processed</p>
                        <h4 class="fw-bold"><?= number_format($total_employees) ?> <?= $total_employees == 1 ? 'Employee' : 'Employees' ?></h4>
                    </div>
                </div>
                <div class="col-6">
                    <div class="card border-0 shadow-sm p-4 text-center">
                        <i class="fas fa-coins fa-2x text-success mb-2"></i>
                        <p class="text-muted small mb-0">Total Net Pay (<?= date('F Y') ?>)</p>
                        <h4 class="fw-bold">₱<?= number_format($total_net, 2) ?></h4>
                    </div>
                </div>
                <div class="col-6">
                    <div class="card border-0 shadow-sm p-4 text-center">
                        <i class="fas fa-calculator fa-2x text-warning mb-2"></i>
                        <p class="text-muted small mb-0">Total Gross Pay</p>
                        <h4 class="fw-bold">₱<?= number_format($total_gross, 2) ?></h4>
                    </div>
                </div>
                <div class="col-6">
                    <div class="card border-0 shadow-sm p-4 text-center">
                        <i class="fas fa-file-alt fa-2x text-info mb-2"></i>
                        <p class="text-muted small mb-0">Total Deductions</p>
                        <h4 class="fw-bold text-danger">₱<?= number_format($total_deductions, 2) ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function togglePeriodInputs() {
    const type = document.getElementById('report_type_select').value;
    const monthGroup = document.getElementById('month_input_group');
    const quarterGroup = document.getElementById('quarter_input_group');
    const yearGroup = document.getElementById('year_input_group');

    if (type === 'quarterly') {
        monthGroup.classList.add('d-none');
        quarterGroup.classList.remove('d-none');
        yearGroup.classList.remove('d-none');
    } else if (type === 'annual') {
        monthGroup.classList.add('d-none');
        quarterGroup.classList.add('d-none');
        yearGroup.classList.remove('d-none');
    } else {
        monthGroup.classList.remove('d-none');
        quarterGroup.classList.add('d-none');
        yearGroup.classList.add('d-none');
    }
}
</script>
<?= $this->endSection() ?>