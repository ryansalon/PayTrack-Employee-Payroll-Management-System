<?php

namespace App\Controllers;

use App\Libraries\PayrollCalculator;
use App\Models\DeductionModel;
use App\Models\EmployeeModel;
use App\Models\OfficeModel;
use App\Models\PayrollModel;
use App\Models\PayrollPeriodModel;

class Deduction extends BaseController
{
    public function manage($employee_id)
    {
        $empModel     = new EmployeeModel();
        $deductModel  = new DeductionModel();
        $payrollModel = new PayrollModel();
        $periodModel  = new PayrollPeriodModel();

        $period = PayrollCalculator::sanitizePeriod($this->request->getVar('period'));
        $isLocked = $periodModel->isLocked($period);
        $isPastUnlocked = (!$isLocked && $period < date('Y-m'));

        $employee = $empModel->select('employees.*, offices.office_name')
                             ->join('offices', 'offices.id = employees.office_id', 'left')
                             ->find($employee_id);
        if (!$employee) {
            return redirect()->to('/deduction?period=' . $period)->with('error', 'Employee not found.');
        }

        $periodPayroll = $payrollModel->where('employee_id', $employee_id)
                                      ->where('payroll_period', $period)
                                      ->first();
        $standingDeduct = $deductModel->where('employee_id', $employee_id)->first() ?? [];

        if ($isPastUnlocked && $periodPayroll) {
            $deductions = PayrollCalculator::extractSnapshot($periodPayroll, $standingDeduct);
            $salaryRate = $periodPayroll['salary_rate_snapshot'] !== null
                ? (float) $periodPayroll['salary_rate_snapshot']
                : max(0.0, round((float) $periodPayroll['gross_pay'] - (float) ($periodPayroll['refund_rata'] ?? 0), 2));
            $atmAccount = $periodPayroll['atm_account_no_snapshot'] ?? ($employee['atm_account_no'] ?? '');
            $refundRata = (float) ($periodPayroll['refund_rata'] ?? 0);
        } else {
            $deductions = PayrollCalculator::normalizeDeductions($standingDeduct);
            $salaryRate = (float) ($employee['salary_rate'] ?? 0);
            $atmAccount = (string) ($employee['atm_account_no'] ?? '');
            $refundRata = (float) ($periodPayroll['refund_rata'] ?? 0);
        }

        return view('deduction/manage', [
            'employee'         => $employee,
            'deductions'       => $deductions,
            'salary_rate'      => $salaryRate,
            'atm_account_no'   => $atmAccount,
            'refund_rata'      => $refundRata,
            'period'           => $period,
            'period_label'     => PayrollCalculator::formatPeriodLabel($period),
            'is_locked'        => $isLocked,
            'is_past_unlocked' => $isPastUnlocked,
        ]);
    }

    public function update()
    {
        $deductModel  = new DeductionModel();
        $empModel     = new EmployeeModel();
        $payrollModel = new PayrollModel();
        $periodModel  = new PayrollPeriodModel();
        $officeModel  = new OfficeModel();

        $empId  = (int) $this->request->getPost('employee_id');
        $period = PayrollCalculator::sanitizePeriod($this->request->getPost('period'));

        $emp = $empModel->find($empId);
        if (!$emp) {
            return redirect()->to('/payroll?period=' . $period)->with('error', 'Employee not found.');
        }

        $deductionData = PayrollCalculator::normalizeDeductions([
            'withholding_tax'     => $this->request->getPost('tax'),
            'loans'               => $this->request->getPost('loans'),
            'government_cont'     => $this->request->getPost('gov'),
            'other_deduct'        => $this->request->getPost('others'),
            'gsis_premium'        => $this->request->getPost('gsis_premium'),
            'gsis_policy'         => $this->request->getPost('gsis_policy'),
            'gsis_other'          => $this->request->getPost('gsis_other'),
            'gsis_ouli'           => $this->request->getPost('gsis_ouli'),
            'gsis_diff'           => $this->request->getPost('gsis_diff'),
            'pagibig_premium'     => $this->request->getPost('pagibig_premium'),
            'pagibig_loan'        => $this->request->getPost('pagibig_loan'),
            'pagibig_mp2'         => $this->request->getPost('pagibig_mp2'),
            'phic'                => $this->request->getPost('phic'),
            'phic_diff'           => $this->request->getPost('phic_diff'),
            'bank_lbp'            => $this->request->getPost('bank_lbp'),
            'bank_other_payables' => $this->request->getPost('bank_other_payables'),
            'bank_mcc'            => $this->request->getPost('bank_mcc'),
            'bank_1stvb'          => $this->request->getPost('bank_1stvb'),
            'bank_rbt'            => $this->request->getPost('bank_rbt'),
        ]);

        $salaryInput = $this->request->getPost('salary_rate');
        $salaryRate  = ($salaryInput !== null && $salaryInput !== '')
            ? PayrollCalculator::toMoney($salaryInput)
            : (float) ($emp['salary_rate'] ?? 0);

        $atmInput   = $this->request->getPost('atm_account_no');
        $atmAccount = $atmInput !== null ? trim((string) $atmInput) : (string) ($emp['atm_account_no'] ?? '');

        $isLocked       = $periodModel->isLocked($period);
        $isPastUnlocked = (!$isLocked && $period < date('Y-m'));

        // Case 1 & 3 (locked or current/future draft): update standing deductions & employee master fields
        if (!$isPastUnlocked) {
            $existingDeduct = $deductModel->where('employee_id', $empId)->first();
            if ($existingDeduct) {
                $deductModel->update($existingDeduct['id'], $deductionData);
            } else {
                $deductModel->insert(array_merge($deductionData, ['employee_id' => $empId]));
            }

            $empModel->skipValidation(true)->update($empId, [
                'salary_rate'    => $salaryRate,
                'atm_account_no' => $atmAccount !== '' ? $atmAccount : null,
            ]);
        }

        // Case 1 (locked period): do not touch the locked period's payroll_records row
        if ($isLocked) {
            return redirect()->to('/payroll?period=' . $period)->with(
                'success',
                "Standing deductions updated for {$emp['full_name']}. Locked period {$period} was left unchanged."
            );
        }

        // Case 2 (past unlocked) & Case 3 (current/future draft): upsert this period's payroll record & snapshot
        $refundRata = PayrollCalculator::toMoney($this->request->getPost('refund_rata') ?? 0);
        $existingPayroll = $payrollModel->where('employee_id', $empId)
                                        ->where('payroll_period', $period)
                                        ->first();

        $existingFirstQ = ($existingPayroll && isset($existingPayroll['first_quincena']) && (float) $existingPayroll['first_quincena'] > 0)
            ? (float) $existingPayroll['first_quincena']
            : null;

        $calc = PayrollCalculator::computePayroll($salaryRate, $refundRata, $deductionData, $existingFirstQ);

        $officeName = $existingPayroll['office_name_snapshot'] ?? null;
        $officeId   = $existingPayroll['office_id_snapshot'] ?? ($emp['office_id'] ? (int) $emp['office_id'] : null);
        if (!$officeName && $officeId) {
            $office = $officeModel->find($officeId);
            $officeName = $office['office_name'] ?? null;
        }

        $payrollData = [
            'period_of_service'       => $existingPayroll['period_of_service'] ?? PayrollCalculator::periodDateRange($period),
            'refund_rata'             => $calc['refund_rata'],
            'gross_pay'               => $calc['gross_pay'],
            'total_deductions'        => $calc['total_deductions'],
            'net_pay'                 => $calc['net_pay'],
            'first_quincena'          => $calc['first_quincena'],
            'second_quincena'         => $calc['second_quincena'],
            'cash_paid'               => $calc['cash_paid'],
            'processed_by'            => session()->get('id') ?: null,
            'deduction_snapshot'      => $calc['deduction_snapshot'],
            'salary_rate_snapshot'    => $calc['salary_rate_snapshot'],
            'employee_code_snapshot'  => $existingPayroll['employee_code_snapshot'] ?? $emp['employee_id'],
            'full_name_snapshot'      => $existingPayroll['full_name_snapshot'] ?? $emp['full_name'],
            'office_id_snapshot'      => $officeId,
            'office_name_snapshot'    => $officeName,
            'position_snapshot'       => $existingPayroll['position_snapshot'] ?? ($emp['position'] ?? ''),
            'atm_account_no_snapshot' => $atmAccount !== '' ? $atmAccount : null,
        ];

        if ($existingPayroll) {
            $payrollModel->update($existingPayroll['id'], $payrollData);
        } else {
            $payrollData['employee_id']    = $empId;
            $payrollData['payroll_period'] = $period;
            $payrollModel->insert($payrollData);
        }

        $msg = $isPastUnlocked
            ? "Historical payroll updated for {$emp['full_name']} ({$period}). Standing rates were left unchanged."
            : "Deductions updated for {$emp['full_name']}.";

        return redirect()->to('/payroll?period=' . $period)->with('success', $msg);
    }

    public function index()
    {
        $empModel    = new EmployeeModel();
        $officeModel = new OfficeModel();
        $periodModel = new PayrollPeriodModel();

        $keyword    = trim((string) $this->request->getVar('search'));
        $officeId   = $this->request->getVar('office_id');
        $bankFilter = $this->request->getVar('bank_filter');
        $gsisFilter = $this->request->getVar('gsis_filter');
        $period     = PayrollCalculator::sanitizePeriod($this->request->getVar('period'));
        $isLocked   = $periodModel->isLocked($period);

        $offices = $officeModel->getOfficesOrdered();
        if ($officeId === null && !empty($offices)) {
            foreach ($offices as $office) {
                if (stripos($office['office_name'], 'MAYOR') !== false) {
                    $officeId = $office['id'];
                    break;
                }
            }
        }

        $empModel->select('employees.*, offices.office_name,
                            deductions.gsis_premium, deductions.gsis_policy, deductions.gsis_other,
                            deductions.gsis_ouli, deductions.gsis_diff,
                            deductions.pagibig_premium, deductions.pagibig_loan, deductions.pagibig_mp2,
                            deductions.phic, deductions.phic_diff, deductions.withholding_tax,
                            deductions.loans, deductions.government_cont, deductions.other_deduct,
                            deductions.bank_lbp, deductions.bank_other_payables, deductions.bank_mcc,
                            deductions.bank_1stvb, deductions.bank_rbt,
                            payroll_records.id as payroll_id, payroll_records.refund_rata,
                            payroll_records.gross_pay, payroll_records.total_deductions,
                            payroll_records.net_pay, payroll_records.first_quincena, payroll_records.second_quincena,
                            payroll_records.deduction_snapshot, payroll_records.salary_rate_snapshot,
                            payroll_records.atm_account_no_snapshot')
                 ->join('offices', 'offices.id = employees.office_id', 'left')
                 ->join('deductions', 'deductions.employee_id = employees.id', 'left')
                 ->join('payroll_records', 'payroll_records.employee_id = employees.id AND payroll_records.payroll_period = ' . $empModel->db->escape($period), 'left');

        if ($isLocked) {
            $empModel->where('payroll_records.id IS NOT NULL', null, false);
        } else {
            $empModel->groupStart()
                     ->where('employees.is_active', 1)
                     ->orWhere('payroll_records.id IS NOT NULL', null, false)
                     ->groupEnd();
        }

        if ($officeId) {
            $empModel->where('employees.office_id', $officeId);
        }
        if ($keyword !== '') {
            $empModel->groupStart()
                     ->like('employees.full_name', $keyword)
                     ->orLike('employees.employee_id', $keyword)
                     ->groupEnd();
        }

        $rawRows = $empModel->orderBy('employees.id', 'ASC')->findAll();
        $records = [];

        foreach ($rawRows as $row) {
            $deducts = !empty($row['payroll_id'])
                ? PayrollCalculator::extractSnapshot($row)
                : PayrollCalculator::normalizeDeductions($row);

            if ($bankFilter) {
                $bankMap = [
                    'lbp'   => 'bank_lbp',
                    'mcc'   => 'bank_mcc',
                    '1stvb' => 'bank_1stvb',
                    'rbt'   => 'bank_rbt',
                    'other' => 'bank_other_payables',
                ];
                $col = $bankMap[$bankFilter] ?? null;
                if ($col && ($deducts[$col] ?? 0) <= 0) {
                    continue;
                }
            }
            if ($gsisFilter) {
                $gsisMap = [
                    'premium' => 'gsis_premium',
                    'policy'  => 'gsis_policy',
                    'other'   => 'gsis_other',
                    'ouli'    => 'gsis_ouli',
                    'diff'    => 'gsis_diff',
                ];
                $col = $gsisMap[$gsisFilter] ?? null;
                if ($col && ($deducts[$col] ?? 0) <= 0) {
                    continue;
                }
            }

            $salaryRate = ($row['salary_rate_snapshot'] !== null)
                ? (float) $row['salary_rate_snapshot']
                : (float) ($row['salary_rate'] ?? 0);
            $refundRata = (float) ($row['refund_rata'] ?? 0);
            $existingQ1 = ($row['first_quincena'] !== null && (float) $row['first_quincena'] > 0)
                ? (float) $row['first_quincena']
                : null;
            $calc = PayrollCalculator::computePayroll($salaryRate, $refundRata, $deducts, $existingQ1, true);

            $row['salary_rate_effective'] = $salaryRate;
            $row['deductions_effective']  = $deducts;
            $row['groups']                = PayrollCalculator::groupTotals($deducts);
            $row['net_pay_effective']     = $calc['net_pay'];
            $row['first_q_effective']     = $calc['first_quincena'];
            $row['second_q_effective']    = $calc['second_quincena'];
            $row['signature_display']     = PayrollCalculator::signatureValue($row);
            $records[] = $row;
        }

        return view('deduction/index', [
            'offices'        => $offices,
            'records'        => $records,
            'search'         => $keyword,
            'office_id'      => $officeId,
            'bank_filter'    => $bankFilter,
            'gsis_filter'    => $gsisFilter,
            'period'         => $period,
            'period_label'   => PayrollCalculator::formatPeriodLabel($period),
            'service_period' => PayrollCalculator::periodDateRange($period),
            'is_locked'      => $isLocked,
        ]);
    }
}
