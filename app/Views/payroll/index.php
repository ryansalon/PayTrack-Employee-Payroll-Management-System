<?= $this->extend('layout/main') ?>

<?= $this->section('content') ?>
<style>
.payroll-table thead th {
    background-color: var(--navy-800) !important;
    color: #fff !important;
}

.payroll-table th,
.payroll-table td {
    border: 1px solid #dee2e6 !important;
}

.payroll-table tbody tr:hover {
    background-color: var(--navy-subtle) !important;
}

.row-selected {
    background-color: var(--navy-subtle) !important;
    border-left: 3px solid var(--navy-600) !important;
}

.row-selected td {
    background-color: var(--navy-subtle) !important;
    color: #1e293b !important;
}

.row-selected td small,
.row-selected td .text-muted {
    color: #4a5f7a !important;
}

.row-selected td .badge {
    color: #ffffff !important;
    background-color: var(--navy-600) !important;
}

.row-selected td .btn {
    color: #ffffff !important;
    background-color: var(--navy-600) !important;
    border-color: var(--navy-600) !important;
}


</style>
<?php
function peso($value) {
    return $value > 0 ? '₱' . number_format($value, 2) : '';
}

?>
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h4 class="fw-bold mb-1">
                Payroll Processing
                <?php if (!empty($is_locked)): ?>
                    <span class="badge bg-danger ms-2 fs-6"><i class="fas fa-lock me-1"></i>LOCKED</span>
                <?php else: ?>
                    <span class="badge bg-secondary ms-2 fs-6">DRAFT</span>
                <?php endif; ?>
            </h4>
            <p class="text-muted small mb-1">Period: <strong><?= esc($period_label ?? date('F Y')) ?></strong></p>
            <p class="text-muted small mb-0">Period of Service: <strong><?= esc($service_period ?? '') ?></strong></p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <?php if (empty($is_locked)): ?>
                <form action="/payroll/process-batch" method="post" class="d-inline" onsubmit="return confirm('Process payroll for <?= $office_id ? 'all active employees in this office' : 'all active employees across all non-RATA offices' ?> for <?= esc($period_label) ?>?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="period" value="<?= esc($period) ?>">
                    <input type="hidden" name="office_id" value="<?= esc($office_id ?? '') ?>">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-bolt me-1"></i> <?= $office_id ? 'Process Office' : 'Process All Active' ?>
                    </button>
                </form>
                <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#lockPeriodModal">
                    <i class="fas fa-lock me-1"></i> Lock Period
                </button>
            <?php else: ?>
                <button type="button" class="btn btn-warning btn-sm text-dark fw-bold" data-bs-toggle="modal" data-bs-target="#unlockPeriodModal">
                    <i class="fas fa-unlock me-1"></i> Unlock Period
                </button>
            <?php endif; ?>
            <a href="/deduction?period=<?= esc($period) ?><?= $office_id ? '&office_id=' . urlencode((string) $office_id) : '' ?>" class="btn btn-outline-dark btn-sm"><i class="fas fa-sliders-h me-1"></i> Manage Deductions</a>
            <a href="/payroll/print?period=<?= esc($period) ?><?= $office_id ? '&office_id=' . urlencode((string) $office_id) : '' ?>" class="btn btn-outline-primary btn-sm" target="_blank"><i class="fas fa-print me-1"></i> Print All</a>
            <a href="/payroll/export?period=<?= esc($period) ?><?= $office_id ? '&office_id=' . urlencode((string) $office_id) : '' ?>" class="btn btn-success btn-sm"><i class="fas fa-file-export me-1"></i> Export All</a>
        </div>
    </div>

    <div class="card border-0 shadow-sm p-3 mb-4 bg-light">
        <form action="/payroll" method="get" class="row g-2 align-items-center">
            <div class="col-md-3">
                <input type="month" name="period" class="form-control border-0 shadow-sm" value="<?= esc($period) ?>" onchange="this.form.submit()">
            </div>
            <div class="col-md-4">
                <select name="office_id" class="form-select border-0 shadow-sm" onchange="this.form.submit()">
                    <option value="">All Offices</option>
                    <?php foreach($offices as $office): ?>
                        <option value="<?= $office['id'] ?>" <?= (isset($office_id) && (string)$office_id === (string)$office['id']) ? 'selected' : '' ?>><?= esc($office['office_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5">
                <div class="input-group shadow-sm">
                    <span class="input-group-text bg-white border-0"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-0" placeholder="Quick find employee ID, name, or ATM..." value="<?= esc($search ?? '') ?>">
                    <button type="submit" class="btn btn-outline-secondary border-0 bg-white">Filter</button>
                </div>
            </div>
        </form>
    </div>

<div class="table-responsive">
        <table class="table table-hover bg-white rounded shadow-sm align-middle payroll-table">
<thead class="text-muted small fw-bold">
    <tr>
        <th rowspan="2" class="text-center" style="vertical-align: middle; width: 40px;">NO.</th>
        <th class="ps-4" rowspan="2" style="vertical-align: middle; width: 150px;">EMPLOYEE</th>
        <th rowspan="2" style="vertical-align: middle; width: 120px;">DESIGNATION</th>
        <th rowspan="2" class="text-center border-start" style="vertical-align: middle; width: 100px;">MONTHLY RATE</th>
        <th rowspan="2" class="text-center border-start" style="vertical-align: middle; width: 100px;">REFUND RATA PERA ACA DIFFERENTIAL</th>
        <th colspan="3" class="text-center border-start">GSIS</th>
        <th colspan="2" class="text-center border-start">PAG-IBIG</th>
        <th rowspan="2" class="text-center border-start" style="vertical-align: middle; width: 100px;">PHIC PHIC-Diff</th>
        <th colspan="3" class="text-center border-start">BANKS / COOP'S</th>
        <th rowspan="2" class="text-center border-start" style="vertical-align: middle; width: 90px;">BIR TAX</th>
        <th rowspan="2" class="text-center border-start" style="vertical-align: middle; width: 90px;">NET PAY</th>
        <th rowspan="2" class="text-center border-start" style="vertical-align: middle; width: 150px;">QUINCENA</th>
        <th rowspan="2" class="text-center border-start" style="vertical-align: middle; width: 100px;">SIGNATURE</th>
        <th rowspan="2" class="text-end pe-4" style="vertical-align: middle; width: 50px;">ACTION</th>
    </tr>
    <tr>
        <th class="text-center small border-start" style="width: 100px;">PREMIUM (Personal) OULI diff</th>
        <th class="text-center small" style="vertical-align: middle; width: 100px;">Conso Policy MPL</th>
        <th class="text-center small" style="vertical-align: middle; width: 100px;">GFAL EMRGYLN MPL LITE CPL</th>
        <th class="text-center small border-start" style="vertical-align: middle; width: 100px;">PREMIUM (Personal)</th>
        <th class="text-center small" style="vertical-align: middle; width: 100px;">SALARY CALAMITY</th>
        <th class="text-center small border-start" style="vertical-align: middle; width: 100px;">LBP Other Payables</th>
        <th class="text-center small" style="vertical-align: middle; width: 80px;">MCC (over)</th>
        <th class="text-center small" style="vertical-align: middle; width: 80px;">1stVB RBT</th>
    </tr>
</thead>
<tbody>
    <?php
    $no = 1;
    $totalFirstQ = 0;
    $totalSecondQ = 0;
    $totalSalaryRate = 0;
    $totalRefund = 0;
    $totalGsisPremium = 0;
    $totalGsisPolicy = 0;
    $totalGsisOther = 0;
    $totalPagibigPremium = 0;
    $totalPagibigLoan = 0;
    $totalPhic = 0;
    $totalBankLbp = 0;
    $totalBankMcc = 0;
    $totalBank1stvb = 0;
    $totalWithholdingTax = 0;
    $totalNetPay = 0;
    foreach($employees as $emp):
    ?>
    <?php
        $g          = $emp['groups'];
        $salaryRate = (float) ($emp['salary_rate'] ?? 0);
        $refundRata = (float) ($emp['refund_rata'] ?? 0);
        $netPay     = (float) $emp['net_pay_effective'];
        $firstQ     = (float) $emp['first_quincena'];
        $secondQ    = (float) $emp['second_quincena'];

        $totalFirstQ += $firstQ;
        $totalSecondQ += $secondQ;
        $totalSalaryRate += $salaryRate;
        $totalRefund += $refundRata;
        $totalGsisPremium += $g['gsis_col1'];
        $totalGsisPolicy += $g['gsis_policy'];
        $totalGsisOther += $g['gsis_other'];
        $totalPagibigPremium += $g['pagibig_premium'];
        $totalPagibigLoan += $g['pagibig_col2'];
        $totalPhic += $g['phic'];
        $totalBankLbp += $g['bank_lbp_col'];
        $totalBankMcc += $g['bank_mcc'];
        $totalBank1stvb += $g['bank_1stvb_col'];
        $totalWithholdingTax += $g['tax'];
        $totalNetPay += $netPay;
    ?>
    <tr class="border-bottom">
        <td class="align-middle text-center" style="width: 40px;"><?= $no++ ?></td>
        <td class="ps-4">
            <div class="fw-bold"><?= esc($emp['full_name']) ?></div>
        </td>
        <td><?= esc($emp['position']) ?></td>
        <td><?= peso($salaryRate) ?></td>
        <td class="text-muted small text-end border-start"><?= peso($refundRata) ?></td>
        <td class="text-danger small text-end border-start"><?= peso($g['gsis_col1']) ?></td>
        <td class="text-danger small text-end"><?= peso($g['gsis_policy']) ?></td>
        <td class="text-danger small text-end"><?= peso($g['gsis_other']) ?></td>
        <td class="text-danger small text-end border-start"><?= peso($g['pagibig_premium']) ?></td>
        <td class="text-danger small text-end"><?= peso($g['pagibig_col2']) ?></td>
        <td class="text-danger small text-end border-start"><?= peso($g['phic']) ?></td>
        <td class="text-danger small text-end border-start"><?= peso($g['bank_lbp_col']) ?></td>
        <td class="text-danger small text-end"><?= peso($g['bank_mcc']) ?></td>
        <td class="text-danger small text-end"><?= peso($g['bank_1stvb_col']) ?></td>
        <td class="text-danger small text-end border-start"><?= peso($g['tax']) ?></td>
        <td class="fw-bold text-success border-start"><?= peso($netPay) ?></td>
        <td class="text-muted small border-start">1st Q: <?= peso($firstQ) ?><br>2nd Q: <?= peso($secondQ) ?></td>
        <td class="text-muted small border-start text-end"><?= esc($emp['signature_display'] ?? '') ?></td>
        <td class="text-center" style="width: 50px;">
            <div class="dropdown">
                <button class="btn btn-link text-dark p-2" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="min-width: 36px; min-height: 36px;">
                    <i class="fas fa-ellipsis-vertical fa-lg"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                    <?php if (empty($is_locked)): ?>
                    <li>
                    <button class="dropdown-item d-flex align-items-center gap-2 process-btn"
                        data-employee-id="<?= $emp['id'] ?>"
                        data-employee-name="<?= esc($emp['full_name']) ?>"
                        data-designation="<?= esc($emp['position']) ?>"
                        data-net-pay="<?= $netPay ?>"
                        data-first-quincena="<?= $firstQ ?>"
                        data-second-quincena="<?= $secondQ ?>"
                        data-bs-toggle="modal"
                        data-bs-target="#processModal">
                            <i class="fas fa-calculator text-primary"></i> Process
                        </button>
                    </li>
                    <?php endif; ?>
                    <li>
                        <a href="/payslip/individual/<?= $emp['id'] ?>?period=<?= esc($period) ?>" class="dropdown-item d-flex align-items-center gap-2" target="_blank">
                            <i class="fas fa-print text-success"></i> Print
                        </a>
                    </li>
                </ul>
            </div>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($employees)): ?>
        <tr><td colspan="19" class="text-center text-muted py-4">No employees found.</td></tr>
    <?php endif; ?>

        <tr class="small">
            <td colspan="16" class="text-end fw-bold">1st Quincena:</td>
            <td class="text-muted border-start fw-bold">₱<?= number_format($totalFirstQ, 2) ?></td>
            <td colspan="1"></td>
            <td colspan="1"></td>
        </tr>
        <tr class="small">
            <td colspan="16" class="text-end fw-bold">2nd Quincena:</td>
            <td class="text-muted border-start fw-bold">₱<?= number_format($totalSecondQ, 2) ?></td>
            <td colspan="1"></td>
            <td colspan="1"></td>
        </tr>
        <tr class="fw-bold">
            <td colspan="3" class="text-center">TOTAL</td>
            <td class="text-end"><?= peso($totalSalaryRate) ?></td>
            <td class="text-end border-start"><?= peso($totalRefund) ?></td>
            <td class="text-end border-start"><?= peso($totalGsisPremium) ?></td>
            <td class="text-end"><?= peso($totalGsisPolicy) ?></td>
            <td class="text-end"><?= peso($totalGsisOther) ?></td>
            <td class="text-end border-start"><?= peso($totalPagibigPremium) ?></td>
            <td class="text-end"><?= peso($totalPagibigLoan) ?></td>
            <td class="text-end border-start"><?= peso($totalPhic) ?></td>
            <td class="text-end border-start"><?= peso($totalBankLbp) ?></td>
            <td class="text-end"><?= peso($totalBankMcc) ?></td>
            <td class="text-end"><?= peso($totalBank1stvb) ?></td>
            <td class="text-end border-start"><?= peso($totalWithholdingTax) ?></td>
            <td class="text-end border-start"><?= peso($totalNetPay) ?></td>
            <td class="text-end border-start"><?= peso($totalFirstQ + $totalSecondQ) ?></td>
            <td colspan="1"></td>
            <td colspan="1"></td>
        </tr>
    </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="processModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-0 rounded-top">
                <h5 class="modal-title fw-bold"><i class="fas fa-calculator me-2"></i>Process Payroll</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <form id="processForm" method="post" action="">
                    <?= csrf_field() ?>
                    <input type="hidden" name="period" value="<?= esc($period) ?>">
                    <input type="hidden" name="office_id" value="<?= esc($office_id ?? '') ?>">
                    <div class="bg-light p-4 border-bottom">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-bold text-uppercase mb-1">Employee Name</label>
                                <div class="fw-bold text-dark" id="modalEmployeeName"></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-bold text-uppercase mb-1">Designation</label>
                                <div class="fw-bold text-dark" id="modalDesignation"></div>
                            </div>
                            <div class="col-12">
                                <div class="card border-0 bg-white shadow-sm">
                                    <div class="card-body py-3 px-4">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <label class="form-label text-muted small fw-bold text-uppercase mb-0">Total Net Pay (After Deductions)</label>
                                            </div>
                                            <div class="fw-bold text-success fs-5" id="modalNetPay"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <label class="form-label text-muted small fw-bold text-uppercase mb-0">Quincena Breakdown</label>
                            <button type="button" id="splitHalfBtn" class="btn btn-outline-primary btn-sm py-1 px-2" style="font-size: 0.75rem;">
                                <i class="fas fa-divide me-1"></i> Split 50/50 Half
                            </button>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="firstQuincena" class="form-label text-muted small fw-bold text-uppercase mb-1">1st Quincena</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white">₱</span>
                                    <input type="text" inputmode="decimal" class="form-control money-format" id="firstQuincena" name="first_quincena" placeholder="0.00">
                                </div>
                                <small class="text-muted" style="font-size: 0.75rem;">Advance / 1st half pay</small>
                            </div>
                            <div class="col-md-6">
                                <label for="secondQuincena" class="form-label text-muted small fw-bold text-uppercase mb-1">2nd Quincena</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white">₱</span>
                                    <input type="text" inputmode="decimal" class="form-control money-format" id="secondQuincena" name="second_quincena" placeholder="0.00" readonly>
                                </div>
                                <small class="text-success fw-bold" id="remainingNetPayText" style="font-size: 0.75rem;">Remaining Net Pay: ₱0.00</small>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 bg-light rounded-bottom">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="processForm" class="btn btn-primary px-4"><i class="fas fa-check me-1"></i> Process Payroll</button>
            </div>
        </div>
    </div>
</div>

<!-- Lock Period Modal -->
<div class="modal fade" id="lockPeriodModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="/payroll/lock" method="post" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="period" value="<?= esc($period) ?>">
            <input type="hidden" name="office_id" value="<?= esc($office_id ?? '') ?>">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-danger"><i class="fas fa-lock me-2"></i>Lock Payroll Period</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted">
                    Locking <strong><?= esc($period_label) ?> (<?= esc($period) ?>)</strong> freezes its processed payroll snapshots so future salary, deduction, or employee edits cannot alter this month's records, payslips, or reports.
                </p>
                <div class="mb-2">
                    <label class="form-label small fw-bold text-muted">NOTES (OPTIONAL)</label>
                    <input type="text" name="notes" class="form-control" placeholder="e.g. Disbursed &amp; submitted to COA">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger fw-bold">Lock Period</button>
            </div>
        </form>
    </div>
</div>

<!-- Unlock Period Modal -->
<div class="modal fade" id="unlockPeriodModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="/payroll/unlock" method="post" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="period" value="<?= esc($period) ?>">
            <input type="hidden" name="office_id" value="<?= esc($office_id ?? '') ?>">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-warning"><i class="fas fa-unlock me-2"></i>Unlock Payroll Period</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-0">
                    Are you sure you want to reopen <strong><?= esc($period_label) ?> (<?= esc($period) ?>)</strong> for edits? Re-lock it once corrections are complete.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-warning text-dark fw-bold">Unlock Period</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const processButtons = document.querySelectorAll('.process-btn');
    const processForm = document.getElementById('processForm');
    const firstQuincenaInput = document.getElementById('firstQuincena');
    const secondQuincenaInput = document.getElementById('secondQuincena');
    const splitHalfBtn = document.getElementById('splitHalfBtn');
    const modalNetPayEl = document.getElementById('modalNetPay');
    const remainingTextEl = document.getElementById('remainingNetPayText');
    let currentNetPay = 0;

    function unformatMoney(value) {
        return (value || '').replace(/,/g, '');
    }

    function addCommas(raw) {
        if (raw === '') return '';
        let [intPart, decPart] = raw.split('.');
        intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        return decPart !== undefined ? `${intPart}.${decPart}` : intPart;
    }

    function updateRemaining(secondQ) {
        if (remainingTextEl) {
            remainingTextEl.textContent = 'Remaining Net Pay: ₱' + secondQ.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }
    }

    processButtons.forEach(function(btn) {
        btn.addEventListener('click', function() {
            const employeeName = btn.getAttribute('data-employee-name');
            const designation = btn.getAttribute('data-designation');
            const netPay = parseFloat(btn.getAttribute('data-net-pay')) || 0;
            const existingFirstQ = parseFloat(btn.getAttribute('data-first-quincena')) || 0;

            currentNetPay = netPay;

            document.getElementById('modalEmployeeName').textContent = employeeName;
            document.getElementById('modalDesignation').textContent = designation;
            modalNetPayEl.textContent = '₱' + netPay.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});

            processForm.action = '/payroll/process/' + btn.getAttribute('data-employee-id');

            let firstQ = (existingFirstQ > 0 && existingFirstQ <= netPay) ? existingFirstQ : Math.round((netPay / 2) * 100) / 100;
            let secondQ = Math.max(0, Math.round((netPay - firstQ) * 100) / 100);

            firstQuincenaInput.value = addCommas(firstQ.toFixed(2));
            secondQuincenaInput.value = addCommas(secondQ.toFixed(2));
            updateRemaining(secondQ);
        });
    });

    if (splitHalfBtn) {
        splitHalfBtn.addEventListener('click', function() {
            const half = Math.round((currentNetPay / 2) * 100) / 100;
            const secondQ = Math.max(0, Math.round((currentNetPay - half) * 100) / 100);
            firstQuincenaInput.value = addCommas(half.toFixed(2));
            secondQuincenaInput.value = addCommas(secondQ.toFixed(2));
            updateRemaining(secondQ);
        });
    }

    function recalculateFromFirstQ() {
        let raw = unformatMoney(firstQuincenaInput.value).replace(/[^0-9.]/g, '');
        const firstDot = raw.indexOf('.');
        if (firstDot !== -1) {
            raw = raw.slice(0, firstDot + 1) + raw.slice(firstDot + 1).replace(/\./g, '');
        }
        firstQuincenaInput.value = addCommas(raw);
        const firstQ = parseFloat(unformatMoney(firstQuincenaInput.value)) || 0;
        const secondQ = Math.max(0, Math.round((currentNetPay - firstQ) * 100) / 100);
        secondQuincenaInput.value = addCommas(secondQ.toFixed(2));
        updateRemaining(secondQ);
    }

    firstQuincenaInput.addEventListener('input', recalculateFromFirstQ);

    firstQuincenaInput.addEventListener('focus', function() {
        const raw = unformatMoney(this.value);
        if (raw === '' || parseFloat(raw) === 0) {
            this.value = '';
        }
    });

    firstQuincenaInput.addEventListener('blur', function() {
        const raw = unformatMoney(this.value);
        let firstQ = parseFloat(raw);
        if (isNaN(firstQ) || firstQ < 0) {
            firstQ = 0;
        } else if (firstQ > currentNetPay) {
            firstQ = currentNetPay;
        }
        this.value = addCommas(firstQ.toFixed(2));
        const secondQ = Math.max(0, Math.round((currentNetPay - firstQ) * 100) / 100);
        secondQuincenaInput.value = addCommas(secondQ.toFixed(2));
        updateRemaining(secondQ);
    });

    processForm.addEventListener('submit', function() {
        document.querySelectorAll('#processForm .money-format').forEach(input => {
            input.value = unformatMoney(input.value);
        });
    });

    let selectedRow = null;

function selectRow(row) {
    if (selectedRow && selectedRow !== row) {
        selectedRow.classList.remove('row-selected');
        selectedRow.setAttribute('aria-selected', 'false');
    }
    row.classList.add('row-selected');
    row.setAttribute('aria-selected', 'true');
    selectedRow = row;
}

function deselectRow(row) {
    row.classList.remove('row-selected');
    row.setAttribute('aria-selected', 'false');
    if (selectedRow === row) selectedRow = null;
}

    document.querySelectorAll('.payroll-table tbody tr').forEach(function(row, index) {
        const firstCell = row.querySelector('td');
        if (!firstCell) return;
        firstCell.style.cursor = 'pointer';
        firstCell.setAttribute('tabindex', '0');
        firstCell.setAttribute('role', 'button');
        firstCell.setAttribute('aria-label', 'Select row ' + (index + 1));

        firstCell.addEventListener('click', function(e) {
            const currentRow = this.closest('tr');
            if (currentRow.classList.contains('row-selected')) {
                deselectRow(currentRow);
            } else {
                selectRow(currentRow);
            }
        });

        firstCell.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                const currentRow = this.closest('tr');
                if (currentRow.classList.contains('row-selected')) {
                    deselectRow(currentRow);
                } else {
                    selectRow(currentRow);
                }
            }
        });
    });

    document.querySelectorAll('.payroll-table tbody tr').forEach(function(row) {
        row.addEventListener('dblclick', function(e) {
            if (this.classList.contains('row-selected')) {
                deselectRow(this);
            } else {
                selectRow(this);
            }
        });
    });
});
</script>

<?= $this->endSection() ?>