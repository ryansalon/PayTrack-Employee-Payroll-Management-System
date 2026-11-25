<?php
function peso($value) {
    return $value > 0 ? '₱' . number_format($value, 2) : '';
}
function fmt($value) {
    $value = (float) ($value ?? 0);
    return $value != 0 ? number_format($value, 2) : '';
}
function safeOfficeName($office_id, $offices) {
    if (!$office_id) return 'All Offices';
    foreach ($offices ?? [] as $o) {
        if ((string) $o['id'] === (string) $office_id) {
            return $o['office_name'];
        }
    }
    return 'All Offices';
}
$lguName        = $settings['lgu_name'] ?? 'LGU-MAHINOG';
$municipality   = $settings['municipality_label'] ?? 'Mahinog, Camiguin';
$mayorName      = $settings['mayor_name'] ?? 'REY LAWRENCE K. TAN';
$mayorTitle     = $settings['mayor_title'] ?? 'Municipal Mayor';
$treasurerName  = $settings['treasurer_name'] ?? 'MARY LUSSEL S. PACTO';
$treasurerTitle = $settings['treasurer_title'] ?? 'Disbursing Officer';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Payroll — <?= esc($period_label ?? date('F Y')) ?></title>
    <link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/vendor/fontawesome/css/all.min.css') ?>">
    <style>
        * { font-family: 'IBM Plex Sans', 'Segoe UI', Arial, sans-serif !important; }
        body { background: #fff; margin: 0; padding: 0; }
        .payroll-table thead th {
            background-color: #0f2a4a !important;
            color: #fff !important;
        }
        .payroll-table th,
        .payroll-table td {
            border: 1px solid #dee2e6 !important;
        }
        .payroll-table tbody tr {
            border: 1px solid #dee2e6 !important;
        }
        .payroll-table { margin-bottom: 0; }
        .text-navy { color: #12305c !important; }
        .print-header-block h4 { font-size: 1.1rem; }
        .print-header-block p { font-size: 0.8rem; }
        .print-header-block p.lh-sm { line-height: 1.3; }
        @media print {
            .no-print { display: none !important; }
            body { background: #fff; }
            .table-responsive { overflow: visible; zoom: 0.75; }
            .payroll-table thead th {
                background-color: #0f2a4a !important;
                color: #fff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                font-size: 0.55rem !important;
            }
            .payroll-table,
            .payroll-table thead th,
            .payroll-table tbody td,
            .payroll-table tfoot td {
                padding: 2px 3px !important;
                font-size: 0.58rem !important;
            }
            .print-footer p { font-size: 0.58rem !important; }
            .print-footer .fw-bold { font-size: 0.65rem !important; }
            .page-break { break-before: page; page-break-before: always; }
            @page {
                size: 8.5in 5.5in landscape;
                margin: 0.15in;
            }
        }
    </style>
</head>
<body>
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
        <div>
            <h5 class="fw-bold mb-0">Payroll — <?= esc($period_label ?? date('F Y')) ?> — <?= esc(safeOfficeName($office_id ?? null, $offices ?? [])) ?></h5>
            <p class="text-muted small mb-0">Period of Service: <?= esc($service_period ?? '') ?> | Paper Target: <strong>1/2 Crosswise (8.5" x 5.5" Landscape)</strong></p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-success btn-sm px-3" onclick="window.print()">
                <i class="fas fa-print me-1"></i> Print (1/2 Crosswise)
            </button>
            <a href="/payroll/export?period=<?= esc($period) ?><?= $office_id ? '&office_id=' . urlencode((string) $office_id) : '' ?>" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-file-excel me-1"></i> Export Excel
            </a>
            <a href="/payroll?period=<?= esc($period) ?><?= $office_id ? '&office_id=' . urlencode((string) $office_id) : '' ?>" class="btn btn-outline-dark btn-sm">
                <i class="fas fa-arrow-left me-1"></i> Back
            </a>
        </div>
    </div>

    <?php
    $pageSize   = count($employees ?? []) > 10 ? (int) ceil(count($employees ?? []) / 2) : 10;
    $chunks     = array_chunk($employees ?? [], max(1, $pageSize));
    $pageNum    = 1;
    $totalPages = count($chunks);
    $globalNo   = 1;
    ?>

    <?php foreach ($chunks as $chunkIdx => $employeesChunk): ?>
    <?php if ($chunkIdx > 0): ?>
    <div class="page-break"></div>
    <?php endif; ?>

    <div class="print-header-block mb-2">
        <div class="text-center mb-1">
            <h4 class="fw-bold mb-0"><?= esc($lguName) ?></h4>
            <p class="mb-0 small">MUNICIPAL PAYROLL</p><br>
        </div>
        <div class="text-center mb-2">
            <p class="small lh-sm mb-0">
                We hereby acknowledge to have received from <strong class="text-decoration-underline"><?= esc($treasurerName) ?></strong>. Treasurer of <strong class="text-decoration-underline"><?= esc($municipality) ?></strong> the sums herein specified opposite our respective names, the same, being full compensation for our services rendered during the period stated below, to the correctness of which we hereby severally certify.
            </p>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-border-bottom-0 payroll-table align-middle">
            <thead class="text-muted small fw-bold">
                <tr>
                    <th rowspan="2" class="text-center border-start" style="vertical-align: middle; width: 40px;">NO.</th>
                    <th rowspan="2" class="ps-4" style="vertical-align: middle; width: 150px;">EMPLOYEE</th>
                    <th rowspan="2" style="vertical-align: middle; width: 120px;">DESIGNATION</th>
                    <th rowspan="2" class="text-center border-start" style="vertical-align: middle; width: 100px;">MONTHLY RATE</th>
                    <th rowspan="2" class="text-center border-start" style="vertical-align: middle; width: 100px;">REFUND RATA PERA ACA DIFFERENTIAL</th>
                    <th colspan="3" class="text-center border-start">GSIS</th>
                    <th colspan="2" class="text-center border-start">PAG-IBIG</th>
                    <th rowspan="2" class="text-center border-start" style="vertical-align: middle; width: 100px;">PHIC PHIC-Diff</th>
                    <th colspan="3" class="text-center border-start">BANKS / COOP'S</th>
                    <th rowspan="2" class="text-center border-start" style="vertical-align: middle; width: 90px;">BIR W/T TAX</th>
                    <th rowspan="2" class="text-center border-start" style="vertical-align: middle; width: 90px;">NET PAY</th>
                    <th rowspan="2" class="text-center border-start" style="vertical-align: middle; width: 150px;">QUINCENA</th>
                    <th rowspan="2" class="text-center border-start" style="vertical-align: middle; width: 100px;">SIGNATURE</th>
                </tr>
                <tr>
                    <th class="text-center" style="width: 100px;">PREMIUM (Personal) OULI diff</th>
                    <th class="text-center border-start" style="vertical-align: middle; width: 100px;">Conso Policy MPL</th>
                    <th class="text-center" style="width: 100px;">GFAL EMRGYLN MPL LITE CPL</th>
                    <th class="text-center border-start" style="vertical-align: middle; width: 100px;">PREMIUM (Personal)</th>
                    <th class="text-center" style="vertical-align: middle; width: 100px;">SALARY CALAMITY / MP2</th>
                    <th class="text-center border-start" style="vertical-align: middle; width: 100px;">LBP Other Payables</th>
                    <th class="text-center border-start" style="vertical-align: middle; width: 80px;">MCC (over)</th>
                    <th class="text-center border-start" style="vertical-align: middle; width: 80px;">1stVB RBT</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $cSalary = $cRefund = $cGsis1 = $cGsis2 = $cGsis3 = 0.0;
                $cPag1 = $cPag2 = $cPhic = $cLbp = $cMcc = $c1stvb = $cTax = 0.0;
                $cNet = $cQ1 = $cQ2 = 0.0;
                foreach ($employeesChunk as $emp):
                    $g = $emp['groups'];
                    $cSalary += (float) $emp['salary_rate'];
                    $cRefund += (float) ($emp['refund_rata'] ?? 0);
                    $cGsis1  += $g['gsis_col1'];
                    $cGsis2  += $g['gsis_policy'];
                    $cGsis3  += $g['gsis_other'];
                    $cPag1   += $g['pagibig_premium'];
                    $cPag2   += $g['pagibig_col2'];
                    $cPhic   += $g['phic'];
                    $cLbp    += $g['bank_lbp_col'];
                    $cMcc    += $g['bank_mcc'];
                    $c1stvb  += $g['bank_1stvb_col'];
                    $cTax    += $g['tax'];
                    $cNet    += (float) $emp['net_pay_effective'];
                    $cQ1     += (float) $emp['first_quincena'];
                    $cQ2     += (float) $emp['second_quincena'];
                ?>
                <tr class="border-bottom">
                    <td class="align-middle text-center" style="width: 40px;"><?= $globalNo++ ?></td>
                    <td class="ps-4"><div class="fw-bold"><?= esc($emp['full_name']) ?></div></td>
                    <td><?= esc($emp['position'] ?? '-') ?></td>
                    <td class="fw-bold text-navy"><?= fmt($emp['salary_rate']) ?></td>
                    <td class="text-end border-start"><?= fmt($emp['refund_rata'] ?? 0) ?></td>
                    <td class="text-end border-start"><?= fmt($g['gsis_col1']) ?></td>
                    <td class="text-end"><?= fmt($g['gsis_policy']) ?></td>
                    <td class="text-end"><?= fmt($g['gsis_other']) ?></td>
                    <td class="text-end border-start"><?= fmt($g['pagibig_premium']) ?></td>
                    <td class="text-end"><?= fmt($g['pagibig_col2']) ?></td>
                    <td class="text-center border-start"><?= fmt($g['phic']) ?></td>
                    <td class="text-end border-start"><?= fmt($g['bank_lbp_col']) ?></td>
                    <td class="text-end"><?= fmt($g['bank_mcc']) ?></td>
                    <td class="text-end"><?= fmt($g['bank_1stvb_col']) ?></td>
                    <td class="text-end border-start"><?= fmt($g['tax']) ?></td>
                    <td class="fw-bold text-success border-start"><?= fmt($emp['net_pay_effective']) ?></td>
                    <td class="border-start">
                        1st Q: <?= peso($emp['first_quincena'] ?? 0) ?><br>
                        2nd Q: <?= peso($emp['second_quincena'] ?? 0) ?>
                    </td>
                    <td class="border-start text-center"><?= esc($emp['signature_display'] ?: '-') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="16" class="text-end fw-bold">1st Quincena:</td>
                    <td class="text-muted border-start fw-bold"><?= peso($cQ1) ?></td>
                    <td class="border-start"></td>
                </tr>
                <tr>
                    <td colspan="16" class="text-end fw-bold">2nd Quincena:</td>
                    <td class="text-muted border-start fw-bold"><?= peso($cQ2) ?></td>
                    <td class="border-start"></td>
                </tr>
                <tr class="fw-bold">
                    <td colspan="3" class="text-center">TOTAL</td>
                    <td class="fw-bold"><?= fmt($cSalary) ?></td>
                    <td class="text-end border-start"><?= fmt($cRefund) ?></td>
                    <td class="text-end border-start"><?= fmt($cGsis1) ?></td>
                    <td class="text-end"><?= fmt($cGsis2) ?></td>
                    <td class="text-end"><?= fmt($cGsis3) ?></td>
                    <td class="text-end border-start"><?= fmt($cPag1) ?></td>
                    <td class="text-end"><?= fmt($cPag2) ?></td>
                    <td class="text-center border-start"><?= fmt($cPhic) ?></td>
                    <td class="text-end border-start"><?= fmt($cLbp) ?></td>
                    <td class="text-end"><?= fmt($cMcc) ?></td>
                    <td class="text-end"><?= fmt($c1stvb) ?></td>
                    <td class="text-end border-start fw-bold"><?= fmt($cTax) ?></td>
                    <td class="fw-bold text-success border-start"><?= peso($cNet) ?></td>
                    <td class="border-start fw-bold"><?= peso($cQ1 + $cQ2) ?></td>
                    <td class="border-start"></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="print-footer mt-3">
        <div style="display: flex; width: 100%; gap: 1rem; align-items: flex-start;">
            <div style="flex: 1; text-align: center;">
                <p class="small lh-sm mb-0">
                    <strong>(1)</strong> I HEREBY CERTIFY on my official oath that the above PAYROLL is correct, and that services above stated have been duly rendered. Payment for such services is also hereby approved from the appropriation indicated.
                </p>
            </div>
            <div style="flex: 1; text-align: center;">
                <p class="small mb-0"><strong>(4) APPROVED:</strong></p>
            </div>
            <div style="flex: 1; text-align: center;">
                <p class="small lh-sm mb-0">
                    <strong>(5)</strong> I HEREBY CERTIFY on my official oath that I have paid in cash to each official and employee whose name appears on the above roll the amount set opposite his name, under column 17, the having signed or marked his name under column 20 above, in my presence and at the time that payment was made to him in acknowledgement of receipt of the money paid him.
                </p>
            </div>
        </div>
        <div style="display: flex; width: 100%; gap: 1rem; margin-top: 1.5rem;">
            <div style="flex: 1; text-align: center;">
                <p class="fw-bold mb-0"><?= esc($mayorName) ?></p>
                <p class="small text-muted mb-0"><?= esc($mayorTitle) ?></p>
            </div>
            <div style="flex: 1; text-align: center;">
                <p class="fw-bold mb-0"><?= esc($mayorName) ?></p>
                <p class="small text-muted mb-0"><?= esc($mayorTitle) ?></p>
            </div>
            <div style="flex: 1; text-align: center;">
                <p class="fw-bold mb-0 text-decoration-underline"><?= esc($treasurerName) ?></p>
                <p class="small text-muted mb-0"><?= esc($treasurerTitle) ?></p>
            </div>
        </div>
    </div>

    <div class="text-center mt-2 no-print">
        <small class="text-muted">Page <?= $pageNum++ ?> of <?= $totalPages ?></small>
    </div>
    <?php endforeach; ?>

    <?php if (empty($employees)): ?>
    <div class="text-center py-4">
        <p class="text-muted">No employees with payroll data for this period.</p>
    </div>
    <?php endif; ?>
</div>

<script>
window.onload = function() {
    window.print();
};
window.addEventListener('afterprint', function() {
    window.close();
});
</script>
</body>
</html>
