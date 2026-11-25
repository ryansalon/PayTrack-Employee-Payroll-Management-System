<?php

use App\Libraries\PayrollCalculator;
use CodeIgniter\Test\CIUnitTestCase;

final class PayrollCalculatorTest extends CIUnitTestCase
{
    public function testAll19DeductionFieldsAndGroupTotalsReconcile(): void
    {
        $raw = [
            'gsis_premium'        => '1,800.00',
            'gsis_policy'         => '500.00',
            'gsis_other'          => '250.50',
            'gsis_ouli'           => '100.00',
            'gsis_diff'           => '50.25',
            'pagibig_premium'     => '200.00',
            'pagibig_loan'        => '300.00',
            'pagibig_mp2'         => '400.00',
            'phic'                => '450.00',
            'phic_diff'           => '25.00',
            'bank_lbp'            => '1,000.00',
            'bank_other_payables' => '150.00',
            'bank_mcc'            => '200.00',
            'bank_1stvb'          => '350.00',
            'bank_rbt'            => '75.00',
            'withholding_tax'     => '1,200.00',
            'loans'               => '110.00',
            'government_cont'     => '90.00',
            'other_deduct'        => '40.00',
        ];

        $norm   = PayrollCalculator::normalizeDeductions($raw);
        $groups = PayrollCalculator::groupTotals($norm);

        $this->assertCount(19, $norm);
        $this->assertSame(1950.25, $groups['gsis_col1']);
        $this->assertSame(2700.75, $groups['gsis']);
        $this->assertSame(700.00, $groups['pagibig_col2']);
        $this->assertSame(900.00, $groups['pagibig']);
        $this->assertSame(475.00, $groups['phic']);
        $this->assertSame(240.00, $groups['other']);
        $this->assertSame(390.00, $groups['other_payables_total']);
        $this->assertSame(1390.00, $groups['bank_lbp_col']);
        $this->assertSame(425.00, $groups['bank_1stvb_col']);
        $this->assertSame(1200.00, $groups['tax']);

        $expectedTotal = 7290.75;
        $this->assertSame($expectedTotal, $groups['total']);
        $this->assertSame($expectedTotal, PayrollCalculator::totalDeductions($norm));

        // Verify stacked columns sum to exact total (matches Excel A..T layout)
        $stackedSum = round(
            $groups['gsis_col1'] + $groups['gsis_policy'] + $groups['gsis_other']
            + $groups['pagibig_premium'] + $groups['pagibig_col2']
            + $groups['phic']
            + $groups['bank_lbp_col'] + $groups['bank_mcc'] + $groups['bank_1stvb_col']
            + $groups['tax'],
            2
        );
        $this->assertSame($expectedTotal, $stackedSum);
    }

    public function testCanonicalGrossPayIncludesRefundRataAndQuincenaSplits(): void
    {
        $calc = PayrollCalculator::computePayroll(20000.00, 5000.00, ['gsis_premium' => 1800.00, 'phic' => 450.00]);

        $this->assertSame(20000.00, $calc['salary_rate_snapshot']);
        $this->assertSame(5000.00, $calc['refund_rata']);
        $this->assertSame(25000.00, $calc['gross_pay']);
        $this->assertSame(2250.00, $calc['total_deductions']);
        $this->assertSame(22750.00, $calc['net_pay']);
        $this->assertSame(11375.00, $calc['first_quincena']);
        $this->assertSame(11375.00, $calc['second_quincena']);

        // Custom valid first_quincena is preserved
        $custom = PayrollCalculator::computePayroll(20000.00, 0.0, ['gsis_premium' => 1000.00], 10000.00);
        $this->assertSame(10000.00, $custom['first_quincena']);
        $this->assertSame(9000.00, $custom['second_quincena']);

        // Oversized first_quincena (> net_pay) falls back to 50/50 split
        $fallback = PayrollCalculator::computePayroll(10000.00, 0.0, ['gsis_premium' => 1000.00], 9500.00);
        $this->assertSame(4500.00, $fallback['first_quincena']);
        $this->assertSame(4500.00, $fallback['second_quincena']);
    }

    public function testRataOnlyVoucherDoesNotDoubleCount(): void
    {
        $calc = PayrollCalculator::computePayroll(0.0, 8500.00, ['withholding_tax' => 500.00]);
        $this->assertSame(0.0, $calc['salary_rate_snapshot']);
        $this->assertSame(8500.00, $calc['gross_pay']);
        $this->assertSame(8000.00, $calc['net_pay']);
    }

    public function testPeriodHelpersAndSignatureFallback(): void
    {
        $this->assertSame('2026-10', PayrollCalculator::sanitizePeriod('2026-10'));
        $this->assertSame(date('Y-m'), PayrollCalculator::sanitizePeriod('invalid'));
        $this->assertSame('October 2026', PayrollCalculator::formatPeriodLabel('2026-10'));
        $this->assertSame('10/01/2026-10/31/2026', PayrollCalculator::periodDateRange('2026-10'));

        $this->assertSame('1234-5678', PayrollCalculator::signatureValue(['atm_account_no' => '1234-5678', 'contact_number' => '09171234567']));
        $this->assertSame('09171234567', PayrollCalculator::signatureValue(['atm_account_no' => '', 'contact_number' => '09171234567']));
    }
}
