<?php

namespace App\Libraries;

use DateTimeImmutable;

class PayrollCalculator
{
    public const DEDUCTION_FIELDS = [
        'gsis_premium',
        'gsis_policy',
        'gsis_other',
        'gsis_ouli',
        'gsis_diff',
        'pagibig_premium',
        'pagibig_loan',
        'pagibig_mp2',
        'phic',
        'phic_diff',
        'bank_lbp',
        'bank_other_payables',
        'bank_mcc',
        'bank_1stvb',
        'bank_rbt',
        'withholding_tax',
        'loans',
        'government_cont',
        'other_deduct',
    ];

    public const DEDUCTION_LABELS = [
        'gsis_premium'        => 'GSIS Personal Premium',
        'gsis_policy'         => 'GSIS Conso / Policy / MPL',
        'gsis_other'          => 'GSIS GFAL / Emergency / CPL',
        'gsis_ouli'           => 'GSIS OULI',
        'gsis_diff'           => 'GSIS Differential',
        'pagibig_premium'     => 'Pag-IBIG Personal Premium',
        'pagibig_loan'        => 'Pag-IBIG Salary Loan',
        'pagibig_mp2'         => 'Pag-IBIG MP2',
        'phic'                => 'PhilHealth (PHIC)',
        'phic_diff'           => 'PhilHealth Differential',
        'bank_lbp'            => 'Bank — LandBank (LBP)',
        'bank_other_payables' => 'Bank — Other Payables',
        'bank_mcc'            => 'Bank — MCC',
        'bank_1stvb'          => 'Bank — 1st Valley Bank',
        'bank_rbt'            => 'Bank — RBT',
        'withholding_tax'     => 'BIR Withholding Tax',
        'loans'               => 'Other Loans',
        'government_cont'     => 'Other Gov. Contributions',
        'other_deduct'        => 'Other Deductions',
    ];

    public static function sanitizePeriod(?string $period): string
    {
        $period = trim((string) $period);
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            return $period;
        }
        return date('Y-m');
    }

    public static function formatPeriodLabel(string $period): string
    {
        $dt = DateTimeImmutable::createFromFormat('!Y-m', self::sanitizePeriod($period));
        return $dt ? $dt->format('F Y') : date('F Y');
    }

    public static function periodStartEnd(string $period): array
    {
        $dt = DateTimeImmutable::createFromFormat('!Y-m', self::sanitizePeriod($period))
            ?: new DateTimeImmutable('first day of this month');
        return [
            'start' => $dt->format('m/01/Y'),
            'end'   => $dt->format('m/t/Y'),
        ];
    }

    public static function periodDateRange(string $period): string
    {
        $se = self::periodStartEnd($period);
        return $se['start'] . '-' . $se['end'];
    }

    public static function toMoney($value): float
    {
        if (is_string($value)) {
            $value = str_replace([',', ' ', '₱'], '', trim($value));
        }
        return is_numeric($value) ? round((float) $value, 2) : 0.0;
    }

    public static function normalizeDeductions(array $raw, bool $allowSignedOther = false): array
    {
        $out = [];
        foreach (self::DEDUCTION_FIELDS as $field) {
            $val = self::toMoney($raw[$field] ?? 0);
            if (!($allowSignedOther && $field === 'other_deduct') && $val < 0) {
                $val = 0.0;
            }
            $out[$field] = $val;
        }
        return $out;
    }

    public static function totalDeductions(array $deductions): float
    {
        $sum = 0.0;
        foreach (self::DEDUCTION_FIELDS as $field) {
            $sum += self::toMoney($deductions[$field] ?? 0);
        }
        return round($sum, 2);
    }

    public static function groupTotals(array $deductions): array
    {
        $d = self::normalizeDeductions($deductions, true);

        $gsisCol1 = round($d['gsis_premium'] + $d['gsis_ouli'] + $d['gsis_diff'], 2);
        $gsis     = round($gsisCol1 + $d['gsis_policy'] + $d['gsis_other'], 2);

        $pagibigCol2 = round($d['pagibig_loan'] + $d['pagibig_mp2'], 2);
        $pagibig     = round($d['pagibig_premium'] + $pagibigCol2, 2);

        $phic = round($d['phic'] + $d['phic_diff'], 2);

        $other              = round($d['loans'] + $d['government_cont'] + $d['other_deduct'], 2);
        $otherPayablesTotal = round($d['bank_other_payables'] + $other, 2);
        $bankLbpCol         = round($d['bank_lbp'] + $otherPayablesTotal, 2);
        $bank1stvbCol       = round($d['bank_1stvb'] + $d['bank_rbt'], 2);
        $banks              = round($d['bank_lbp'] + $d['bank_other_payables'] + $d['bank_mcc'] + $d['bank_1stvb'] + $d['bank_rbt'], 2);

        $tax   = $d['withholding_tax'];
        $total = round($gsis + $pagibig + $phic + $banks + $tax + $other, 2);

        return [
            'gsis_col1'            => $gsisCol1,
            'gsis_policy'          => $d['gsis_policy'],
            'gsis_other'           => $d['gsis_other'],
            'gsis'                 => $gsis,
            'pagibig_premium'      => $d['pagibig_premium'],
            'pagibig_col2'         => $pagibigCol2,
            'pagibig'              => $pagibig,
            'phic'                 => $phic,
            'other_payables_total' => $otherPayablesTotal,
            'bank_lbp_col'         => $bankLbpCol,
            'bank_mcc'             => $d['bank_mcc'],
            'bank_1stvb_col'       => $bank1stvbCol,
            'banks'                => $banks,
            'other'                => $other,
            'tax'                  => $tax,
            'total'                => $total,
        ];
    }

    /**
     * Canonical payroll math:
     * gross_pay = salary_rate + refund_rata
     * net_pay   = gross_pay - total_deductions
     */
    public static function computePayroll(
        float $salaryRate,
        float $refundRata,
        array $deductions,
        ?float $firstQuincena = null,
        bool $allowSignedOther = false
    ): array {
        $salaryRate      = max(0.0, round($salaryRate, 2));
        $refundRata      = round($refundRata, 2);
        $normDeductions  = self::normalizeDeductions($deductions, $allowSignedOther);
        $grossPay        = round($salaryRate + $refundRata, 2);
        $totalDeductions = self::totalDeductions($normDeductions);
        $netPay          = round($grossPay - $totalDeductions, 2);

        if ($firstQuincena !== null && $firstQuincena > 0 && $firstQuincena <= $netPay) {
            $firstQ = round($firstQuincena, 2);
        } else {
            $firstQ = round($netPay / 2, 2);
        }
        $secondQ = round($netPay - $firstQ, 2);

        return [
            'salary_rate_snapshot' => $salaryRate,
            'refund_rata'          => $refundRata,
            'gross_pay'            => $grossPay,
            'total_deductions'     => $totalDeductions,
            'net_pay'              => $netPay,
            'first_quincena'       => $firstQ,
            'second_quincena'      => $secondQ,
            'cash_paid'            => $netPay,
            'deductions'           => $normDeductions,
            'deduction_snapshot'   => json_encode($normDeductions),
        ];
    }

    public static function extractSnapshot(array $row, ?array $fallback = null): array
    {
        if (!empty($row['deduction_snapshot']) && is_string($row['deduction_snapshot'])) {
            $decoded = json_decode($row['deduction_snapshot'], true);
            if (is_array($decoded)) {
                return self::normalizeDeductions($decoded, true);
            }
        }
        return self::normalizeDeductions($fallback ?? $row, true);
    }

    public static function isRataOfficeName(?string $name): bool
    {
        $norm = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $name));
        return strpos($norm, 'RATA') !== false;
    }

    public static function signatureValue(array $row): string
    {
        $atm = trim((string) ($row['atm_account_no_snapshot'] ?? $row['atm_account_no'] ?? ''));
        if ($atm !== '') {
            return $atm;
        }
        return trim((string) ($row['contact_number'] ?? ''));
    }
}
