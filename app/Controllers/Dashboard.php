<?php

namespace App\Controllers;

use App\Libraries\PayrollCalculator;
use App\Models\AppSettingModel;
use App\Models\PayrollPeriodModel;
use DateTimeImmutable;

class Dashboard extends BaseController
{
    public function index()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/auth/login');
        }

        $db          = \Config\Database::connect();
        $period      = PayrollCalculator::sanitizePeriod($this->request->getVar('period'));
        $periodModel = new PayrollPeriodModel();
        $settings    = (new AppSettingModel())->getAllSettings();

        $lastBackupAt  = $settings['last_backup_at'] ?? '';
        $backupOverdue = ($lastBackupAt === '' || strtotime($lastBackupAt) < strtotime('-7 days'));

        $officeRows    = $db->table('offices')->select('id, office_name')->get()->getResultArray();
        $rataOfficeIds = [];
        foreach ($officeRows as $officeRow) {
            if (PayrollCalculator::isRataOfficeName($officeRow['office_name'] ?? '')) {
                $rataOfficeIds[] = (int) $officeRow['id'];
            }
        }

        $empCountQuery = $db->table('employees')->where('is_active', 1);
        if (!empty($rataOfficeIds)) {
            $empCountQuery->groupStart()
                ->whereNotIn('office_id', $rataOfficeIds)
                ->orWhere('office_id IS NULL')
                ->groupEnd();
        }
        $totalEmployees = $empCountQuery->countAllResults();

        $officeCountQuery = $db->table('offices');
        if (!empty($rataOfficeIds)) {
            $officeCountQuery->whereNotIn('id', $rataOfficeIds);
        }
        $totalOffices = $officeCountQuery->countAllResults();

        $processedQuery = $db->table('payroll_records pr')
            ->join('employees e', 'e.id = pr.employee_id', 'left')
            ->where('pr.payroll_period', $period);
        if (!empty($rataOfficeIds)) {
            $processedQuery->groupStart()
                ->whereNotIn('COALESCE(pr.office_id_snapshot, e.office_id)', $rataOfficeIds)
                ->orWhere('COALESCE(pr.office_id_snapshot, e.office_id) IS NULL', null, false)
                ->groupEnd();
        }
        $processedCount = $processedQuery->countAllResults();

        $summary = $db->table('payroll_records')
            ->selectSum('gross_pay')
            ->selectSum('total_deductions')
            ->selectSum('net_pay')
            ->selectSum('refund_rata')
            ->selectSum('cash_paid')
            ->where('payroll_period', $period)
            ->get()
            ->getRow();

        $allTimeSummary = $db->table('payroll_records')
            ->selectSum('gross_pay')
            ->selectSum('total_deductions')
            ->selectSum('net_pay')
            ->selectSum('cash_paid')
            ->select('COUNT(*) as total_records')
            ->get()
            ->getRow();

        // Real 6-month trend ending at selected period (0 for months with no processed payroll — never fabricated)
        $rawTrend = $db->table('payroll_records')
            ->select('payroll_period, SUM(gross_pay) as gross, SUM(total_deductions) as deductions, SUM(net_pay) as net', false)
            ->groupBy('payroll_period')
            ->get()
            ->getResultArray();
        $recordsByPeriod = [];
        foreach ($rawTrend as $r) {
            $recordsByPeriod[$r['payroll_period']] = $r;
        }

        $anchor       = DateTimeImmutable::createFromFormat('!Y-m', $period) ?: new DateTimeImmutable('first day of this month');
        $monthlyTrend = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthDt     = $anchor->modify("-{$i} months");
            $periodKey   = $monthDt->format('Y-m');
            $periodLabel = $monthDt->format('M Y');
            $rec         = $recordsByPeriod[$periodKey] ?? null;

            $monthlyTrend[] = [
                'payroll_period' => $periodLabel,
                'gross'          => $rec ? (float) $rec['gross'] : 0.0,
                'deductions'     => $rec ? (float) $rec['deductions'] : 0.0,
                'net'            => $rec ? (float) $rec['net'] : 0.0,
            ];
        }

        // All 19 deduction fields breakdown (from period snapshots when records exist, else standing deductions)
        $periodRows = $db->table('payroll_records pr')
            ->select('pr.deduction_snapshot, pr.total_deductions, pr.gross_pay, pr.net_pay,
                      pr.full_name_snapshot, pr.employee_code_snapshot, pr.office_name_snapshot,
                      e.full_name as live_full_name, e.employee_id as live_emp_code,
                      o.office_name as live_office_name,
                      d.gsis_premium, d.gsis_policy, d.gsis_other, d.gsis_ouli, d.gsis_diff,
                      d.pagibig_premium, d.pagibig_loan, d.pagibig_mp2, d.phic, d.phic_diff,
                      d.withholding_tax, d.loans, d.government_cont, d.other_deduct,
                      d.bank_lbp, d.bank_other_payables, d.bank_mcc, d.bank_1stvb, d.bank_rbt')
            ->join('employees e', 'e.id = pr.employee_id', 'left')
            ->join('offices o', 'o.id = COALESCE(pr.office_id_snapshot, e.office_id)', 'left')
            ->join('deductions d', 'd.employee_id = pr.employee_id', 'left')
            ->where('pr.payroll_period', $period)
            ->get()->getResultArray();

        $totalsByField = array_fill_keys(PayrollCalculator::DEDUCTION_FIELDS, 0.0);
        if (!empty($periodRows)) {
            foreach ($periodRows as $row) {
                $d = PayrollCalculator::extractSnapshot($row);
                foreach (PayrollCalculator::DEDUCTION_FIELDS as $f) {
                    $totalsByField[$f] = round($totalsByField[$f] + $d[$f], 2);
                }
            }
        } else {
            $allDeducts = $db->table('deductions')->get()->getResultArray();
            foreach ($allDeducts as $row) {
                $d = PayrollCalculator::normalizeDeductions($row);
                foreach (PayrollCalculator::DEDUCTION_FIELDS as $f) {
                    $totalsByField[$f] = round($totalsByField[$f] + $d[$f], 2);
                }
            }
        }

        $deductionsData = [];
        foreach (PayrollCalculator::DEDUCTION_FIELDS as $field) {
            if ($totalsByField[$field] > 0) {
                $deductionsData[] = [
                    'label' => PayrollCalculator::DEDUCTION_LABELS[$field],
                    'value' => $totalsByField[$field],
                ];
            }
        }

        // Payroll by office (snapshot-aware)
        $officeTotals = [];
        foreach ($periodRows as $row) {
            $oName = $row['office_name_snapshot'] ?: ($row['live_office_name'] ?? 'Unassigned');
            if (!isset($officeTotals[$oName])) {
                $officeTotals[$oName] = ['office_name' => $oName, 'gross' => 0.0, 'deductions' => 0.0, 'net' => 0.0];
            }
            $officeTotals[$oName]['gross']      += (float) $row['gross_pay'];
            $officeTotals[$oName]['deductions'] += (float) $row['total_deductions'];
            $officeTotals[$oName]['net']        += (float) $row['net_pay'];
        }
        $payrollByOffice = array_values($officeTotals);
        usort($payrollByOffice, fn ($a, $b) => $b['gross'] <=> $a['gross']);

        // Top 10 payroll records (by gross pay)
        $recentPayroll = [];
        foreach ($periodRows as $row) {
            $recentPayroll[] = [
                'full_name'        => $row['full_name_snapshot'] ?: ($row['live_full_name'] ?? ''),
                'emp_code'         => $row['employee_code_snapshot'] ?: ($row['live_emp_code'] ?? ''),
                'office_name'      => $row['office_name_snapshot'] ?: ($row['live_office_name'] ?? ''),
                'gross_pay'        => (float) $row['gross_pay'],
                'total_deductions' => (float) $row['total_deductions'],
                'net_pay'          => (float) $row['net_pay'],
            ];
        }
        usort($recentPayroll, fn ($a, $b) => $b['gross_pay'] <=> $a['gross_pay']);
        $recentPayroll = array_slice($recentPayroll, 0, 10);

        // Top 5 deduction holders across ALL 19 fields
        $sumExpr = implode(' + ', array_map(fn ($f) => "COALESCE(d.{$f}, 0)", PayrollCalculator::DEDUCTION_FIELDS));
        $topDeductionsQuery = $db->table('employees e')
            ->select("e.id, e.employee_id as emp_code, e.full_name, o.office_name, ({$sumExpr}) as total_deduct", false)
            ->join('offices o', 'o.id = e.office_id', 'left')
            ->join('deductions d', 'd.employee_id = e.id', 'left')
            ->where('e.is_active', 1);
        if (!empty($rataOfficeIds)) {
            $topDeductionsQuery->groupStart()
                ->whereNotIn('e.office_id', $rataOfficeIds)
                ->orWhere('e.office_id IS NULL')
                ->groupEnd();
        }
        $topDeductionsAll = $topDeductionsQuery->get()->getResultArray();
        $topDeductions    = array_values(array_filter($topDeductionsAll, fn ($r) => (float) $r['total_deduct'] > 0));
        usort($topDeductions, fn ($a, $b) => (float) $b['total_deduct'] <=> (float) $a['total_deduct']);
        $topDeductions = array_slice($topDeductions, 0, 5);

        $employeesByOfficeQuery = $db->table('employees e')
            ->select('o.office_name, COUNT(*) as count', false)
            ->join('offices o', 'o.id = e.office_id', 'left')
            ->where('e.is_active', 1);
        if (!empty($rataOfficeIds)) {
            $employeesByOfficeQuery->groupStart()
                ->whereNotIn('e.office_id', $rataOfficeIds)
                ->orWhere('e.office_id IS NULL')
                ->groupEnd();
        }
        $employeesByOffice = $employeesByOfficeQuery
            ->groupBy('o.office_name')
            ->orderBy('count', 'DESC')
            ->get()
            ->getResultArray();

        $empStatusQuery = $db->table('employees')->where('is_active', 1);
        if (!empty($rataOfficeIds)) {
            $empStatusQuery->groupStart()
                ->whereNotIn('office_id', $rataOfficeIds)
                ->orWhere('office_id IS NULL')
                ->groupEnd();
        }
        $employmentStatus = $empStatusQuery
            ->select('employment_status, COUNT(*) as count', false)
            ->groupBy('employment_status')
            ->get()
            ->getResultArray();

        return view('dashboard/index', [
            'period'                   => $period,
            'period_label'             => PayrollCalculator::formatPeriodLabel($period),
            'is_locked'                => $periodModel->isLocked($period),
            'last_backup_at'           => $lastBackupAt,
            'backup_overdue'           => $backupOverdue,
            'total_employees'          => $totalEmployees,
            'total_offices'            => $totalOffices,
            'processed_payroll'        => $processedCount,
            'pending_payroll'          => max(0, $totalEmployees - $processedCount),
            'total_gross_pay'          => (float) ($summary->gross_pay ?? 0),
            'total_deductions'         => (float) ($summary->total_deductions ?? 0),
            'total_net_pay'            => (float) ($summary->net_pay ?? 0),
            'total_refund_rata'        => (float) ($summary->refund_rata ?? 0),
            'total_cash_paid'          => (float) ($summary->cash_paid ?? 0),
            'all_time_gross'           => (float) ($allTimeSummary->gross_pay ?? 0),
            'all_time_deductions'      => (float) ($allTimeSummary->total_deductions ?? 0),
            'all_time_net'             => (float) ($allTimeSummary->net_pay ?? 0),
            'all_time_cash'            => (float) ($allTimeSummary->cash_paid ?? 0),
            'all_time_records_count'   => (int) ($allTimeSummary->total_records ?? 0),
            'monthly_trend'            => $monthlyTrend,
            'deductions_breakdown'     => $deductionsData,
            'payroll_by_office'        => $payrollByOffice,
            'employees_by_office'      => $employeesByOffice,
            'recent_payroll'           => $recentPayroll,
            'top_deductions'           => $topDeductions,
            'employment_status_counts' => $employmentStatus,
        ]);
    }
}
