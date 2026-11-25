<?php

namespace App\Controllers;

use App\Libraries\PayrollCalculator;
use App\Models\AppSettingModel;
use App\Models\DeductionModel;
use App\Models\EmployeeModel;
use App\Models\OfficeModel;
use App\Models\PayrollModel;

class Payslip extends BaseController
{
    public function index()
    {
        $officeModel = new OfficeModel();
        return view('payslip/search', ['offices' => $officeModel->getOfficesOrdered()]);
    }

    public function preview($employee_id)
    {
        $period   = $this->request->getVar('period');
        $settings = (new AppSettingModel())->getAllSettings();
        $db       = \Config\Database::connect();

        $builder = $db->table('payroll_records pr')
            ->select('pr.*, e.full_name as live_full_name, e.position as live_position, e.salary_rate as live_salary_rate,
                      d.gsis_premium, d.gsis_policy, d.gsis_other, d.gsis_ouli, d.gsis_diff,
                      d.pagibig_premium, d.pagibig_loan, d.pagibig_mp2, d.phic, d.phic_diff,
                      d.withholding_tax, d.loans, d.government_cont, d.other_deduct,
                      d.bank_lbp, d.bank_other_payables, d.bank_mcc, d.bank_1stvb, d.bank_rbt')
            ->join('employees e', 'e.id = pr.employee_id', 'left')
            ->join('deductions d', 'd.employee_id = pr.employee_id', 'left')
            ->where('pr.employee_id', (int) $employee_id);

        if ($period) {
            $builder->where('pr.payroll_period', PayrollCalculator::sanitizePeriod($period));
        }
        $row = $builder->orderBy('pr.payroll_period', 'DESC')->get()->getRowArray();

        if (!$row) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $deductions = PayrollCalculator::extractSnapshot($row);
        $groups     = PayrollCalculator::groupTotals($deductions);
        $refundRata = (float) ($row['refund_rata'] ?? 0);
        $basicPay   = $row['salary_rate_snapshot'] !== null
            ? (float) $row['salary_rate_snapshot']
            : max(0.0, round((float) ($row['gross_pay'] ?? 0) - $refundRata, 2));

        $row['full_name']  = $row['full_name_snapshot'] ?: ($row['live_full_name'] ?? '');
        $row['position']   = $row['position_snapshot'] ?: ($row['live_position'] ?? '');
        $row['basic_pay']  = $basicPay;
        $row['deductions'] = $deductions;
        $row['groups']     = $groups;

        return view('payslip/preview', [
            'payslip'  => $row,
            'settings' => $settings,
        ]);
    }

    public function individual($employee_id)
    {
        $payrollModel   = new PayrollModel();
        $employeeModel  = new EmployeeModel();
        $deductionModel = new DeductionModel();
        $officeModel    = new OfficeModel();
        $settings       = (new AppSettingModel())->getAllSettings();

        $periodParam = $this->request->getVar('period');
        $payrollQuery = $payrollModel->where('employee_id', (int) $employee_id);
        if ($periodParam) {
            $payrollQuery->where('payroll_period', PayrollCalculator::sanitizePeriod($periodParam));
        }
        $payroll = $payrollQuery->orderBy('payroll_period', 'DESC')->first();

        $employee = $employeeModel->find($employee_id);
        if (!$employee && !$payroll) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $standingDeduct = $employee ? ($deductionModel->where('employee_id', (int) $employee_id)->first() ?? []) : [];
        $deductions     = $payroll
            ? PayrollCalculator::extractSnapshot($payroll, $standingDeduct)
            : PayrollCalculator::normalizeDeductions($standingDeduct);

        $period = $payroll['payroll_period'] ?? PayrollCalculator::sanitizePeriod($periodParam);
        $se     = PayrollCalculator::periodStartEnd($period);

        $refundRata = (float) ($payroll['refund_rata'] ?? 0);
        if ($payroll && $payroll['salary_rate_snapshot'] !== null) {
            $basicPay = (float) $payroll['salary_rate_snapshot'];
        } elseif ($payroll) {
            $basicPay = max(0.0, round((float) $payroll['gross_pay'] - $refundRata, 2));
        } else {
            $basicPay = (float) ($employee['salary_rate'] ?? 0);
        }

        $calc = PayrollCalculator::computePayroll(
            $basicPay,
            $refundRata,
            $deductions,
            ($payroll && isset($payroll['first_quincena']) && (float) $payroll['first_quincena'] > 0) ? (float) $payroll['first_quincena'] : null,
            true
        );

        $officeName = $payroll['office_name_snapshot'] ?? '';
        if ($officeName === '' && !empty($employee['office_id'])) {
            $office     = $officeModel->find($employee['office_id']);
            $officeName = $office['office_name'] ?? '';
        }

        return view('payslip/individual', [
            'employee'         => [
                'full_name'  => ($payroll['full_name_snapshot'] ?? '') ?: ($employee['full_name'] ?? ''),
                'position'   => ($payroll['position_snapshot'] ?? '') ?: ($employee['position'] ?? ''),
                'basic_pay'  => $basicPay,
                'refund_rata'=> $refundRata,
                'gross_pay'  => $payroll ? (float) $payroll['gross_pay'] : $calc['gross_pay'],
                'total_deductions' => $payroll ? (float) $payroll['total_deductions'] : $calc['total_deductions'],
                'net_pay'    => $payroll ? (float) $payroll['net_pay'] : $calc['net_pay'],
                'first_quincena'  => $payroll ? (float) $payroll['first_quincena'] : $calc['first_quincena'],
                'second_quincena' => $payroll ? (float) $payroll['second_quincena'] : $calc['second_quincena'],
            ],
            'deductions'       => $deductions,
            'office_name'      => $officeName,
            'period'           => $period,
            'period_start'     => $se['start'],
            'period_end'       => $se['end'],
            'settings'         => $settings,
        ]);
    }

    public function batchPrint()
    {
        $officeId = $this->request->getVar('office_id');
        $period   = PayrollCalculator::sanitizePeriod($this->request->getVar('period'));
        $settings = (new AppSettingModel())->getAllSettings();
        $db       = \Config\Database::connect();

        $builder = $db->table('payroll_records pr')
            ->select('pr.*, e.full_name as live_full_name, e.position as live_position, e.employee_id as live_emp_code,
                      d.gsis_premium, d.gsis_policy, d.gsis_other, d.gsis_ouli, d.gsis_diff,
                      d.pagibig_premium, d.pagibig_loan, d.pagibig_mp2, d.phic, d.phic_diff,
                      d.withholding_tax, d.loans, d.government_cont, d.other_deduct,
                      d.bank_lbp, d.bank_other_payables, d.bank_mcc, d.bank_1stvb, d.bank_rbt')
            ->join('employees e', 'e.id = pr.employee_id', 'left')
            ->join('deductions d', 'd.employee_id = pr.employee_id', 'left')
            ->where('pr.payroll_period', $period);

        if ($officeId) {
            $builder->where('COALESCE(pr.office_id_snapshot, e.office_id) =', (int) $officeId, false);
        }

        $rows    = $builder->orderBy('COALESCE(pr.full_name_snapshot, e.full_name)', 'ASC', false)->get()->getResultArray();
        $records = [];
        foreach ($rows as $row) {
            $deducts           = PayrollCalculator::extractSnapshot($row);
            $row['full_name']  = $row['full_name_snapshot'] ?: ($row['live_full_name'] ?? '');
            $row['position']   = $row['position_snapshot'] ?: ($row['live_position'] ?? '');
            $row['emp_code']   = $row['employee_code_snapshot'] ?: ($row['live_emp_code'] ?? '');
            $row['deductions'] = $deducts;
            $row['groups']     = PayrollCalculator::groupTotals($deducts);
            $records[]         = $row;
        }

        return view('payslip/batch_print', [
            'records'  => $records,
            'settings' => $settings,
        ]);
    }
}
