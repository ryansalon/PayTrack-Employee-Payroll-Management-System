<?php

namespace App\Controllers;

use App\Libraries\PayrollCalculator;
use App\Models\OfficeModel;
use App\Models\PayrollModel;

class Report extends BaseController
{
    public function index()
    {
        $officeModel  = new OfficeModel();
        $payrollModel = new PayrollModel();

        $period  = PayrollCalculator::sanitizePeriod($this->request->getVar('period'));
        $summary = $payrollModel->getPayrollSummary($period);

        return view('report/index', [
            'offices'          => $officeModel->getOfficesOrdered(),
            'period'           => $period,
            'total_gross'      => (float) ($summary['gross_pay'] ?? 0),
            'total_deductions' => (float) ($summary['total_deductions'] ?? 0),
            'total_net'        => (float) ($summary['net_pay'] ?? 0),
            'total_employees'  => $payrollModel->where('payroll_period', $period)->countAllResults(),
        ]);
    }

    public function generate()
    {
        $officeModel = new OfficeModel();
        $db          = \Config\Database::connect();

        $type     = $this->request->getVar('report_type') ?? 'period';
        $period   = PayrollCalculator::sanitizePeriod($this->request->getVar('period'));
        $quarter  = $this->request->getVar('quarter') ?? 'Q1';
        $year     = preg_replace('/[^0-9]/', '', (string) ($this->request->getVar('year') ?? date('Y'))) ?: date('Y');
        $officeId = $this->request->getVar('office_id') ?? 'all';

        $data = [
            'report_type'     => $type,
            'period'          => $period,
            'quarter'         => $quarter,
            'year'            => $year,
            'office_id'       => $officeId,
            'periodFormatted' => PayrollCalculator::formatPeriodLabel($period),
            'office_name'     => 'All Offices',
        ];

        if ($officeId !== 'all' && $officeId !== '') {
            $office = $officeModel->find($officeId);
            $data['office_name'] = $office['office_name'] ?? 'Unknown';
        }

        if ($type === 'quarterly') {
            $quarterMonths = [
                'Q1' => ["{$year}-01", "{$year}-02", "{$year}-03"],
                'Q2' => ["{$year}-04", "{$year}-05", "{$year}-06"],
                'Q3' => ["{$year}-07", "{$year}-08", "{$year}-09"],
                'Q4' => ["{$year}-10", "{$year}-11", "{$year}-12"],
            ];
            $periods                 = $quarterMonths[$quarter] ?? $quarterMonths['Q1'];
            $data['periodFormatted'] = "{$quarter} {$year}";
            $data['results']         = $this->querySnapshotRows($db, $periods, $officeId);
            $data['report_title']    = "Quarterly Payroll Summary ({$quarter} {$year})";
        } elseif ($type === 'annual') {
            $periods = [];
            for ($m = 1; $m <= 12; $m++) {
                $periods[] = sprintf('%s-%02d', $year, $m);
            }
            $data['periodFormatted'] = $year;
            $data['results']         = $this->querySnapshotRows($db, $periods, $officeId);
            $data['report_title']    = "Annual Payroll Summary ({$year})";
        } elseif ($type === 'office') {
            $rows     = $this->querySnapshotRows($db, [$period], 'all');
            $grouped  = [];
            foreach ($rows as $row) {
                $oName = $row['office_name'] ?: 'Unassigned';
                if (!isset($grouped[$oName])) {
                    $grouped[$oName] = [
                        'office_name'      => $oName,
                        'employees'        => [],
                        'total_gross'      => 0.0,
                        'total_deductions' => 0.0,
                        'total_net'        => 0.0,
                        'count'            => 0,
                    ];
                }
                $grouped[$oName]['employees'][]       = $row;
                $grouped[$oName]['total_gross']      += (float) $row['gross_pay'];
                $grouped[$oName]['total_deductions'] += (float) $row['total_deductions'];
                $grouped[$oName]['total_net']        += (float) $row['net_pay'];
                $grouped[$oName]['count']++;
            }
            $data['results']      = array_values($grouped);
            $data['office_name']  = 'All Offices';
            $data['report_title'] = 'Office-wise Payroll Summary (' . $data['periodFormatted'] . ')';
        } elseif ($type === 'deductions') {
            $data['results']      = $this->querySnapshotRows($db, [$period], $officeId);
            $data['report_title'] = 'Deduction Analysis (' . $data['periodFormatted'] . ')';
        } else {
            $data['results']      = $this->querySnapshotRows($db, [$period], $officeId);
            $data['report_title'] = 'Monthly Payroll Record (' . $data['periodFormatted'] . ')';
        }

        return view('report/summary', $data);
    }

    private function querySnapshotRows($db, array $periods, $officeId): array
    {
        $builder = $db->table('payroll_records pr')
            ->select('pr.*, e.full_name as live_full_name, e.position as live_position,
                      e.employee_id as live_emp_code, o.office_name as live_office_name,
                      d.gsis_premium, d.gsis_policy, d.gsis_other, d.gsis_ouli, d.gsis_diff,
                      d.pagibig_premium, d.pagibig_loan, d.pagibig_mp2, d.phic, d.phic_diff,
                      d.withholding_tax, d.loans, d.government_cont, d.other_deduct,
                      d.bank_lbp, d.bank_other_payables, d.bank_mcc, d.bank_1stvb, d.bank_rbt')
            ->join('employees e', 'e.id = pr.employee_id', 'left')
            ->join('offices o', 'o.id = COALESCE(pr.office_id_snapshot, e.office_id)', 'left')
            ->join('deductions d', 'd.employee_id = pr.employee_id', 'left')
            ->whereIn('pr.payroll_period', $periods);

        if ($officeId !== 'all' && $officeId !== '' && $officeId !== null) {
            $builder->where('COALESCE(pr.office_id_snapshot, e.office_id) =', (int) $officeId, false);
        }

        $rows = $builder->orderBy('pr.payroll_period', 'ASC')
                        ->orderBy('COALESCE(pr.full_name_snapshot, e.full_name)', 'ASC', false)
                        ->get()->getResultArray();

        $out = [];
        foreach ($rows as $row) {
            $deducts            = PayrollCalculator::extractSnapshot($row);
            $row['full_name']   = $row['full_name_snapshot'] ?: ($row['live_full_name'] ?? 'Unknown');
            $row['position']    = $row['position_snapshot'] ?: ($row['live_position'] ?? '');
            $row['emp_code']    = $row['employee_code_snapshot'] ?: ($row['live_emp_code'] ?? '');
            $row['office_name'] = $row['office_name_snapshot'] ?: ($row['live_office_name'] ?? 'Unassigned');
            $row['groups']      = PayrollCalculator::groupTotals($deducts);
            $out[]              = $row;
        }
        return $out;
    }
}
