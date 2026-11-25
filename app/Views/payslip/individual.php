<?php
use App\Libraries\PayrollCalculator;

function peso($value) {
    $value = (float) $value;
    return $value != 0 ? '₱' . number_format($value, 2) : '₱0.00';
}

$lguName         = $settings['lgu_name'] ?? 'LGU-MAHINOG';
$basicPay        = (float) ($employee['basic_pay'] ?? 0);
$rata            = (float) ($employee['refund_rata'] ?? 0);
$totalEarnings   = (float) ($employee['gross_pay'] ?? ($basicPay + $rata));
$totalDeductions = (float) ($employee['total_deductions'] ?? PayrollCalculator::totalDeductions($deductions));
$netPay          = (float) ($employee['net_pay'] ?? ($totalEarnings - $totalDeductions));
$firstHalf       = (float) ($employee['first_quincena'] ?? 0);
$secondHalf      = (float) ($employee['second_quincena'] ?? 0);

// Always show the 4 core statutory items, plus every other non-zero deduction so lines sum to Total Deductions
$coreKeys = ['gsis_premium', 'pagibig_premium', 'phic', 'withholding_tax'];
$deductionLines = [];
foreach (PayrollCalculator::DEDUCTION_FIELDS as $field) {
    $amount = (float) ($deductions[$field] ?? 0);
    if (in_array($field, $coreKeys, true) || $amount != 0.0) {
        $deductionLines[] = [
            'label'  => PayrollCalculator::DEDUCTION_LABELS[$field] ?? $field,
            'amount' => $amount,
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip — <?= esc($employee['full_name'] ?? '') ?></title>
    <style>
        * { font-family: Arial, sans-serif !important; box-sizing: border-box; }
        body { background: #fff; margin: 0; padding: 0; color: #000; }
        .payslip-page { width: 100%; max-width: 100%; margin: 0 auto; padding: 35px 55px 40px 55px; }
        .payslip-header { text-align: center; margin-bottom: 8px; }
        .payslip-header .logo-area { text-align: left; margin-bottom: 10px; }
        .payslip-header .logo-area img { height: 70px; width: auto; }
        .payslip-header h1 { font-size: 20px; font-weight: bold; text-transform: uppercase; margin: 0; letter-spacing: 2px; }
        .divider { border-top: 2px solid #000; margin-top: 8px; margin-bottom: 20px; }
        .info-section { margin-bottom: 40px; }
        .info-row { display: flex; justify-content: space-between; align-items: flex-start; }
        .info-col { width: 50%; }
        .info-item { margin-bottom: 6px; font-size: 14px; }
        .info-item .label { font-weight: normal; margin-right: 8px; }
        .info-item .value { font-weight: bold; text-transform: uppercase; }
        .info-col.right { text-align: right; }
        .details-section { margin-bottom: 50px; }
        .details-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 20px; }
        .details-col { width: 50%; }
        .details-col.left { padding-right: 10px; }
        .details-col.right { padding-left: 10px; }
        .section-title { font-size: 16px; font-weight: bold; text-transform: uppercase; margin-bottom: 8px; padding-bottom: 4px; border-bottom: 1px solid #000; }
        .detail-item { display: flex; justify-content: space-between; align-items: center; padding: 3px 0; font-size: 13px; border-bottom: 1px dotted #ccc; }
        .detail-item .label { flex: 1; }
        .detail-item .amount { text-align: right; font-weight: normal; min-width: 120px; }
        .total-box { display: flex; justify-content: space-between; align-items: center; margin-top: 25px; gap: 20px; }
        .total-item { width: 50%; border-top: 2px solid #000; border-bottom: 2px solid #000; padding: 8px 0; font-size: 15px; font-weight: bold; text-transform: uppercase; display: flex; justify-content: space-between; }
        .net-pay-box { margin-top: 25px; border-top: 2px solid #000; border-bottom: 3px double #000; padding: 10px 0; font-size: 16px; font-weight: bold; text-transform: uppercase; display: flex; justify-content: space-between; align-items: center; }
        .half-pay-box { margin-top: 20px; display: flex; gap: 20px; }
        .half-item { width: 50%; border-top: 2px solid #000; border-bottom: 2px solid #000; padding: 8px 0; font-size: 15px; font-weight: bold; text-transform: uppercase; display: flex; justify-content: space-between; align-items: center; }
        @media print {
            body { background: #fff; }
            .payslip-page { padding: 20px 40px 20px 40px; }
            @page { size: A4 portrait; margin: 20px 40px 20px 40px; }
        }
    </style>
</head>
<body>

<div class="payslip-page">
    <div class="payslip-header">
        <div class="logo-area">
            <img src="<?= base_url('mahinoglgu.png') ?>" alt="<?= esc($lguName) ?> Seal">
        </div>
        <h1><?= esc($lguName) ?></h1>
        <h1>Municipal Payroll</h1>
    </div>

    <div class="divider"></div>

    <div class="info-section">
        <div class="info-row">
            <div class="info-col">
                <div class="info-item">
                    <span class="label">Office:</span>
                    <span class="value"><?= esc($office_name ?? '') ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Name:</span>
                    <span class="value"><?= esc($employee['full_name'] ?? '') ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Designation:</span>
                    <span class="value"><?= esc($employee['position'] ?? '') ?></span>
                </div>
            </div>
            <div class="info-col right">
                <div class="info-item">
                    <span class="label">Pay Period Start Date:</span>
                    <span class="value"><?= esc($period_start ?? '') ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Pay Period End Date:</span>
                    <span class="value"><?= esc($period_end ?? '') ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="details-section">
        <div class="details-row">
            <div class="details-col left">
                <div class="section-title">Earnings:</div>
                <?php if ($basicPay > 0 || $rata <= 0): ?>
                <div class="detail-item">
                    <span class="label">Basic Pay</span>
                    <span class="amount"><?= peso($basicPay) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($rata > 0): ?>
                <div class="detail-item">
                    <span class="label">Refund / RATA (PERA/ACA Diff.)</span>
                    <span class="amount"><?= peso($rata) ?></span>
                </div>
                <?php endif; ?>
            </div>
            <div class="details-col right">
                <div class="section-title">Deductions:</div>
                <?php foreach ($deductionLines as $line): ?>
                <div class="detail-item">
                    <span class="label"><?= esc($line['label']) ?></span>
                    <span class="amount"><?= peso($line['amount']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="total-box">
        <div class="total-item">
            <span class="label">Total Earnings</span>
            <span class="amount"><?= peso($totalEarnings) ?></span>
        </div>
        <div class="total-item">
            <span class="label">Total Deductions</span>
            <span class="amount"><?= peso($totalDeductions) ?></span>
        </div>
    </div>

    <div class="net-pay-box">
        <span class="label">NET PAY FOR THE MONTH</span>
        <span class="amount"><?= peso($netPay) ?></span>
    </div>

    <div class="half-pay-box">
        <div class="half-item">
            <span>1ST HALF</span>
            <span><?= peso($firstHalf) ?></span>
        </div>
        <div class="half-item">
            <span>2ND HALF</span>
            <span><?= peso($secondHalf) ?></span>
        </div>
    </div>
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
