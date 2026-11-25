<?php

namespace App\Controllers;

use App\Libraries\PayrollCalculator;
use App\Models\DeductionModel;
use App\Models\EmployeeModel;
use App\Models\OfficeModel;
use App\Models\PayrollModel;
use App\Models\PayrollPeriodModel;

class Employee extends BaseController
{
    public function index()
    {
        $model       = new EmployeeModel();
        $officeModel = new OfficeModel();

        $officeFilter = $this->request->getVar('office_id');
        $search       = $this->request->getVar('search');
        $statusFilter = $this->request->getVar('status') ?? 'active';

        // Exclude RATA and VICE-SB RATA from offices dropdown in Employee Records
        $data['offices'] = $officeModel->getOfficesOrdered(true);

        if ($officeFilter === null && !empty($data['offices'])) {
            foreach ($data['offices'] as $office) {
                if (stripos($office['office_name'], 'MAYOR') !== false) {
                    $officeFilter = $office['id'];
                    break;
                }
            }
        }

        // Resolve the actually selected office name for the header
        $selectedOfficeName = 'All Offices';
        if ($officeFilter) {
            foreach ($data['offices'] as $office) {
                if ((string) $office['id'] === (string) $officeFilter) {
                    $selectedOfficeName = $office['office_name'];
                    break;
                }
            }
        }

        $allOffices    = $officeModel->findAll();
        $rataOfficeIds = [];
        foreach ($allOffices as $o) {
            if (PayrollCalculator::isRataOfficeName($o['office_name'] ?? '')) {
                $rataOfficeIds[] = (int) $o['id'];
            }
        }

        $query = $model->select('employees.*, offices.office_name')
                       ->join('offices', 'offices.id = employees.office_id', 'left');

        if ($statusFilter === 'active') {
            $query->where('employees.is_active', 1);
        } elseif ($statusFilter === 'inactive') {
            $query->where('employees.is_active', 0);
        }

        if ($officeFilter) {
            $query->where('employees.office_id', $officeFilter);
        } elseif (!empty($rataOfficeIds)) {
            $query->groupStart()
                  ->whereNotIn('employees.office_id', $rataOfficeIds)
                  ->orWhere('employees.office_id IS NULL')
                  ->groupEnd();
        }

        if ($search) {
            $query->groupStart()
                  ->like('employees.full_name', $search)
                  ->orLike('employees.employee_id', $search)
                  ->orLike('employees.atm_account_no', $search)
                  ->groupEnd();
        }

        $data['employees']            = $query->orderBy('employees.full_name', 'ASC')->findAll();
        $data['office_id']            = $officeFilter;
        $data['selected_office_name'] = $selectedOfficeName;
        $data['search']               = $search;
        $data['status_filter']        = $statusFilter;

        return view('employee/index', $data);
    }

    public function store()
    {
        $model = new EmployeeModel();
        $post  = $this->request->getPost();

        if (!$model->save($post)) {
            return redirect()->back()->withInput()->with('errors', $model->errors());
        }

        return redirect()->to('/employee')->with('success', 'Employee registered successfully.');
    }

    public function update($id)
    {
        $model    = new EmployeeModel();
        $existing = $model->find($id);
        if (!$existing) {
            return redirect()->to('/employee')->with('error', 'Employee not found.');
        }

        $post = $this->request->getPost();
        // Ensure employee_id and id are passed so unique validation ignores this row
        $post['id']          = $id;
        $post['employee_id'] = $post['employee_id'] ?? $existing['employee_id'];

        if (!$model->update($id, $post)) {
            return redirect()->back()->withInput()->with('errors', $model->errors());
        }

        return redirect()->to('/employee')->with('success', 'Employee updated successfully.');
    }

    public function delete($id)
    {
        $model        = new EmployeeModel();
        $payrollModel = new PayrollModel();
        $emp          = $model->find($id);

        if (!$emp) {
            return redirect()->to('/employee')->with('error', 'Employee not found.');
        }

        // Soft-deactivate if the employee has historical payroll records so closed months stay intact
        $hasHistory = $payrollModel->where('employee_id', $id)->countAllResults() > 0;
        if ($hasHistory) {
            $model->skipValidation(true)->update($id, ['is_active' => 0]);
            return redirect()->to('/employee')->with('success', $emp['full_name'] . ' deactivated (payroll history preserved).');
        }

        (new DeductionModel())->where('employee_id', $id)->delete();
        $model->delete($id);
        return redirect()->to('/employee')->with('success', 'Employee removed.');
    }

    public function reactivate($id)
    {
        $model = new EmployeeModel();
        $emp   = $model->find($id);
        if (!$emp) {
            return redirect()->to('/employee')->with('error', 'Employee not found.');
        }

        $model->skipValidation(true)->update($id, ['is_active' => 1]);
        return redirect()->to('/employee?status=inactive')->with('success', $emp['full_name'] . ' reactivated.');
    }

    public function deleteAll()
    {
        if (session()->get('role') !== 'admin') {
            return redirect()->to('/employee')->with('error', 'Administrator access is required.');
        }

        if ($this->request->getPost('confirm') !== 'DELETE') {
            return redirect()->to('/employee')->with('error', 'Please type DELETE to confirm removing all records.');
        }

        $deductModel  = new DeductionModel();
        $payrollModel = new PayrollModel();
        $empModel     = new EmployeeModel();

        $payrollModel->emptyTable();
        $deductModel->emptyTable();
        $empModel->emptyTable();

        return redirect()->to('/employee')->with('success', 'All employee records deleted.');
    }

    public function create()
    {
        $officeModel          = new OfficeModel();
        $model                = new EmployeeModel();
        $data['generated_id'] = $model->nextCode('EMP');
        $data['offices']      = $officeModel->getOfficesOrdered(true);

        return view('employee/create', $data);
    }

    public function createJo()
    {
        $officeModel          = new OfficeModel();
        $model                = new EmployeeModel();
        $data['generated_id'] = $model->nextCode('JO');
        $data['offices']      = $officeModel->getOfficesOrdered(true);

        return view('employee/create_jo', $data);
    }

    public function edit($id)
    {
        $model       = new EmployeeModel();
        $officeModel = new OfficeModel();

        $data['employee'] = $model->find($id);
        $data['offices']  = $officeModel->getOfficesOrdered(true);

        if (!$data['employee']) {
            return redirect()->to('/employee')->with('error', 'Employee not found.');
        }

        return view('employee/edit', $data);
    }

    public function import()
    {
        $officeModel  = new OfficeModel();
        $model        = new EmployeeModel();
        $deductModel  = new DeductionModel();
        $payrollModel = new PayrollModel();
        $periodModel  = new PayrollPeriodModel();

        $period = PayrollCalculator::sanitizePeriod($this->request->getPost('period'));
        if ($periodModel->isLocked($period)) {
            return redirect()->to('/employee')->with('error', "Period {$period} is locked. Unlock it first to import payroll.");
        }

        $file = $this->request->getFile('payroll_file');
        if (!$file || !$file->isValid()) {
            return redirect()->to('/employee')->with('error', 'Please select a valid Excel file to import.');
        }

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getTempName());
        $year        = date('Y');
        $processedBy = session()->get('id') ?: null;

        $imported = 0;
        $updated  = 0;
        $skipped  = 0;

        foreach ($spreadsheet->getSheetNames() as $sheetName) {
            $normalized      = strtolower(trim(str_replace('-', ' ', $sheetName)));
            $isRataOnlySheet = ($normalized === 'rata' || $normalized === 'vice sb rata');

            $officeName = trim(preg_replace('/\s+\d+\s+\d+\s*$/', '', $sheetName));
            $office     = $officeModel->where('office_name', $officeName)->first();
            $officeId   = $office ? (int) $office['id'] : (int) $officeModel->insert(['office_name' => $officeName]);

            $rows = $spreadsheet->getSheetByName($sheetName)->toArray();

            if ($isRataOnlySheet) {
                $result    = $this->importRataOnlySheet($rows, $model, $payrollModel, $officeId, $officeName, $year, $period, $processedBy);
                $imported += $result['imported'];
                $updated  += $result['updated'];
                continue;
            }

            foreach ($rows as $i => $row) {
                $no          = $row[0] ?? null;
                $fullName    = trim((string) ($row[1] ?? ''));
                $designation = trim((string) ($row[2] ?? ''));
                $salaryRate  = $row[4] ?? null;

                if (!is_numeric($no) || $fullName === '' || is_numeric($fullName)) {
                    continue;
                }

                $rawSalary      = is_string($salaryRate) ? str_replace([',', ' '], '', $salaryRate) : $salaryRate;
                $hasValidSalary = is_numeric($rawSalary) && (float) $rawSalary > 0;

                $blockEnd = $i + 1;
                while (isset($rows[$blockEnd])) {
                    $blockNo    = $rows[$blockEnd][0] ?? null;
                    $blockName  = trim((string) ($rows[$blockEnd][1] ?? ''));
                    $blockLabel = trim((string) ($rows[$blockEnd][16] ?? ''));
                    if (is_numeric($blockNo) && $blockName !== '' && !is_numeric($blockName)) {
                        break;
                    }
                    if (strcasecmp(trim((string) $blockNo), 'Total') === 0) {
                        break;
                    }
                    if (stripos($blockLabel, 'quincena') !== false) {
                        break;
                    }
                    $blockEnd++;
                }

                $deductionData = $this->parseNormalBlockDeductions($rows, $i, $blockEnd);

                $contactNumber = '';
                for ($r = $i; $r < $blockEnd; $r++) {
                    $rawContact = trim((string) ($rows[$r][19] ?? ''));
                    if ($rawContact !== '' && preg_match('/^\d{7,15}$/', $rawContact)) {
                        $contactNumber = $rawContact;
                        break;
                    }
                }

                $refundRata = 0.0;
                for ($r = $i; $r < $blockEnd; $r++) {
                    $rawRefund = trim((string) ($rows[$r][5] ?? ''));
                    if ($rawRefund !== '' && $rawRefund !== '-') {
                        $refundRata = PayrollCalculator::toMoney($rawRefund);
                        break;
                    }
                }

                $firstQuincena  = PayrollCalculator::toMoney($row[17] ?? 0);
                $secondQuincena = 0.0;
                for ($r = $i + 1; $r < $blockEnd; $r++) {
                    $rawQuincena = trim((string) ($rows[$r][17] ?? ''));
                    if ($rawQuincena !== '' && $rawQuincena !== '-') {
                        $secondQuincena = PayrollCalculator::toMoney($rawQuincena);
                        break;
                    }
                }

                $sheetNetPayRaw = trim((string) ($row[16] ?? ''));
                $hasSheetNetPay = $sheetNetPayRaw !== '' && $sheetNetPayRaw !== '-';
                $sheetNetPay    = $hasSheetNetPay ? PayrollCalculator::toMoney($sheetNetPayRaw) : null;

                $existing = $model->where('office_id', $officeId)
                                  ->where('full_name', $fullName)
                                  ->first();

                if ($existing) {
                    $empUpdate = ['position' => $designation ?: $existing['position'], 'is_active' => 1];
                    if ($hasValidSalary) {
                        $empUpdate['salary_rate'] = (float) $rawSalary;
                    }
                    if ($contactNumber !== '') {
                        $empUpdate['contact_number'] = $contactNumber;
                    }
                    $model->skipValidation(true)->update($existing['id'], $empUpdate);
                    $updated++;
                    $empId   = (int) $existing['id'];
                    $empCode = $existing['employee_id'];
                    $empPos  = $empUpdate['position'];
                    $empAtm  = $existing['atm_account_no'] ?? null;
                } else {
                    $empCode = $model->nextCode('EMP', $year);
                    $empPos  = $designation ?: 'N/A';
                    $empAtm  = null;
                    $empId   = (int) $model->insert([
                        'employee_id'       => $empCode,
                        'full_name'         => $fullName,
                        'office_id'         => $officeId,
                        'position'          => $empPos,
                        'contact_number'    => $contactNumber !== '' ? $contactNumber : null,
                        'salary_rate'       => $hasValidSalary ? (float) $rawSalary : 0,
                        'employment_status' => 'Regular',
                        'is_active'         => 1,
                    ]);

                    if (!$empId) {
                        log_message('error', 'Employee import failed for "{name}": {errors}', [
                            'name'   => $fullName,
                            'errors' => implode('; ', $model->errors() ?? []),
                        ]);
                        $skipped++;
                        continue;
                    }
                    $imported++;
                }

                $salaryVal = $hasValidSalary ? (float) $rawSalary : (float) ($existing['salary_rate'] ?? 0);
                $grossPay  = round($salaryVal + $refundRata, 2);

                // Reconcile against explicit sheet NET PAY when present
                $mappedTotal = PayrollCalculator::totalDeductions($deductionData);
                if ($sheetNetPay !== null && $sheetNetPay > 0) {
                    $delta = round(($grossPay - $sheetNetPay) - $mappedTotal, 2);
                    if ($delta != 0.0) {
                        $deductionData['other_deduct'] = round($deductionData['other_deduct'] + $delta, 2);
                    }
                }

                // Reset all 19 deduction fields on upsert so re-import never double-counts
                $existingDeduction = $deductModel->where('employee_id', $empId)->first();
                if ($existingDeduction) {
                    $deductModel->update($existingDeduction['id'], $deductionData);
                } else {
                    $deductModel->insert(array_merge($deductionData, ['employee_id' => $empId]));
                }

                $calc = PayrollCalculator::computePayroll(
                    $salaryVal,
                    $refundRata,
                    $deductionData,
                    $firstQuincena > 0 ? $firstQuincena : null,
                    true
                );

                // Preserve explicit sheet second quincena if both quincenas were provided and reconcile
                if ($firstQuincena > 0 && $secondQuincena > 0 && abs(($firstQuincena + $secondQuincena) - $calc['net_pay']) < 0.02) {
                    $calc['first_quincena']  = $firstQuincena;
                    $calc['second_quincena'] = $secondQuincena;
                }

                $payrollData = [
                    'period_of_service'       => PayrollCalculator::periodDateRange($period),
                    'refund_rata'             => $calc['refund_rata'],
                    'gross_pay'               => $calc['gross_pay'],
                    'total_deductions'        => $calc['total_deductions'],
                    'net_pay'                 => $calc['net_pay'],
                    'first_quincena'          => $calc['first_quincena'],
                    'second_quincena'         => $calc['second_quincena'],
                    'cash_paid'               => $calc['cash_paid'],
                    'processed_by'            => $processedBy,
                    'deduction_snapshot'      => $calc['deduction_snapshot'],
                    'salary_rate_snapshot'    => $calc['salary_rate_snapshot'],
                    'employee_code_snapshot'  => $empCode,
                    'full_name_snapshot'      => $fullName,
                    'office_id_snapshot'      => $officeId,
                    'office_name_snapshot'    => $officeName,
                    'position_snapshot'       => $empPos,
                    'atm_account_no_snapshot' => $empAtm,
                ];

                $existingPayroll = $payrollModel->where('employee_id', $empId)
                                                ->where('payroll_period', $period)
                                                ->first();
                if ($existingPayroll) {
                    $payrollModel->update($existingPayroll['id'], $payrollData);
                } else {
                    $payrollData['employee_id']    = $empId;
                    $payrollData['payroll_period'] = $period;
                    $payrollModel->insert($payrollData);
                }
            }
        }

        $message = "Import complete for {$period}: {$imported} added, {$updated} updated.";
        if ($skipped > 0) {
            $message .= " {$skipped} row(s) skipped due to validation errors — check logs.";
        }

        return redirect()->to('/employee')->with('success', $message);
    }

    /**
     * Parses a normal 4-row LGU-Mahinog employee block into all 19 normalized deduction fields,
     * matching Payroll::export()'s sub-row layout and summing any overflow rows into the parent field.
     */
    private function parseNormalBlockDeductions(array $rows, int $start, int $end): array
    {
        $d = PayrollCalculator::normalizeDeductions([]);

        // Map (col => [rowOffset => fieldName]) matching Payroll::export()
        $subRowMap = [
            6  => [0 => 'gsis_premium', 2 => 'gsis_ouli', 3 => 'gsis_diff'],
            7  => [0 => 'gsis_policy'],
            8  => [0 => 'gsis_other'],
            9  => [0 => 'pagibig_premium'],
            10 => [0 => 'pagibig_loan', 2 => 'pagibig_mp2'],
            11 => [0 => 'phic', 3 => 'phic_diff'],
            12 => [0 => 'bank_lbp', 2 => 'bank_other_payables', 3 => 'other_deduct'],
            13 => [0 => 'bank_mcc'],
            14 => [0 => 'bank_1stvb', 2 => 'bank_rbt'],
            15 => [0 => 'withholding_tax'],
        ];

        for ($r = $start; $r < $end; $r++) {
            $offset = $r - $start;
            foreach ($subRowMap as $col => $offsetMap) {
                $val = PayrollCalculator::toMoney($rows[$r][$col] ?? 0);
                if ($val == 0.0) {
                    continue;
                }
                $field = $offsetMap[$offset] ?? $offsetMap[0];
                $d[$field] = round($d[$field] + $val, 2);
            }
        }

        return $d;
    }

    private function importRataOnlySheet(
        array $rows,
        EmployeeModel $model,
        PayrollModel $payrollModel,
        int $officeId,
        string $officeName,
        string $year,
        string $period,
        ?int $processedBy
    ): array {
        $rataCol   = $this->findHeaderColumn($rows, ['rata']);
        $netPayCol = $this->findHeaderColumn($rows, ['netpay']);

        $gsisCol    = $this->findHeaderColumn($rows, ['gsis']);
        $pagibigCol = $this->findHeaderColumn($rows, ['pagibig']);
        $phicCol    = $this->findHeaderColumn($rows, ['phic']);
        $lbpCol     = $this->findHeaderColumn($rows, ['lbp']);
        $mccCol     = $this->findHeaderColumn($rows, ['mcc']);
        $vbCol      = $this->findHeaderColumn($rows, ['1stvb']);
        $taxCol     = $this->findHeaderColumn($rows, ['birwttax', 'birtax']);

        if ($rataCol === null) {
            return ['imported' => 0, 'updated' => 0];
        }

        $deductModel = new DeductionModel();
        $imported    = 0;
        $updated     = 0;

        foreach ($rows as $i => $row) {
            $no          = $row[0] ?? null;
            $fullName    = trim((string) ($row[1] ?? ''));
            $designation = trim((string) ($row[2] ?? ''));

            if (!is_numeric($no) || $fullName === '' || is_numeric($fullName)) {
                continue;
            }

            $blockEnd = $i + 1;
            while (isset($rows[$blockEnd])) {
                $blockNo   = $rows[$blockEnd][0] ?? null;
                $blockName = trim((string) ($rows[$blockEnd][1] ?? ''));
                if (is_numeric($blockNo) && $blockName !== '' && !is_numeric($blockName)) {
                    break;
                }
                if (strcasecmp(trim((string) $blockNo), 'Total') === 0) {
                    break;
                }
                $blockEnd++;
            }

            $rataAmount = $this->firstNonBlankInColumn($rows, $i, $blockEnd, $rataCol);
            $sheetNet   = $netPayCol !== null ? $this->firstNonBlankInColumn($rows, $i, $blockEnd, $netPayCol) : null;

            $deductionData = PayrollCalculator::normalizeDeductions([
                'gsis_premium'    => $gsisCol    !== null ? $this->firstNonBlankInColumn($rows, $i, $blockEnd, $gsisCol)    : 0,
                'pagibig_premium' => $pagibigCol !== null ? $this->firstNonBlankInColumn($rows, $i, $blockEnd, $pagibigCol) : 0,
                'phic'            => $phicCol    !== null ? $this->firstNonBlankInColumn($rows, $i, $blockEnd, $phicCol)    : 0,
                'bank_lbp'        => $lbpCol     !== null ? $this->firstNonBlankInColumn($rows, $i, $blockEnd, $lbpCol)     : 0,
                'bank_mcc'        => $mccCol     !== null ? $this->firstNonBlankInColumn($rows, $i, $blockEnd, $mccCol)     : 0,
                'bank_1stvb'      => $vbCol      !== null ? $this->firstNonBlankInColumn($rows, $i, $blockEnd, $vbCol)      : 0,
                'withholding_tax' => $taxCol     !== null ? $this->firstNonBlankInColumn($rows, $i, $blockEnd, $taxCol)     : 0,
            ]);

            $mappedTotal = PayrollCalculator::totalDeductions($deductionData);
            if ($sheetNet !== null && $sheetNet > 0) {
                $unmapped = round(($rataAmount - $sheetNet) - $mappedTotal, 2);
                if ($unmapped != 0.0) {
                    $deductionData['other_deduct'] = $unmapped;
                }
            }

            $existing = $model->where('office_id', $officeId)->where('full_name', $fullName)->first();
            if ($existing) {
                $empPos = $designation ?: $existing['position'];
                $model->skipValidation(true)->update($existing['id'], ['position' => $empPos, 'is_active' => 1]);
                $updated++;
                $empId   = (int) $existing['id'];
                $empCode = $existing['employee_id'];
                $empAtm  = $existing['atm_account_no'] ?? null;
            } else {
                $empCode = $model->nextCode('EMP', $year);
                $empPos  = $designation ?: 'N/A';
                $empAtm  = null;
                $empId   = (int) $model->insert([
                    'employee_id'       => $empCode,
                    'full_name'         => $fullName,
                    'office_id'         => $officeId,
                    'position'          => $empPos,
                    'salary_rate'       => 0,
                    'employment_status' => 'Regular',
                    'is_active'         => 1,
                ]);
                if (!$empId) {
                    continue;
                }
                $imported++;
            }

            $existingDeduction = $deductModel->where('employee_id', $empId)->first();
            if ($existingDeduction) {
                $deductModel->update($existingDeduction['id'], $deductionData);
            } else {
                $deductModel->insert(array_merge($deductionData, ['employee_id' => $empId]));
            }

            $calc = PayrollCalculator::computePayroll(0.0, $rataAmount, $deductionData, null, true);
            $payrollData = [
                'period_of_service'       => PayrollCalculator::periodDateRange($period),
                'refund_rata'             => $calc['refund_rata'],
                'gross_pay'               => $calc['gross_pay'],
                'total_deductions'        => $calc['total_deductions'],
                'net_pay'                 => $calc['net_pay'],
                'first_quincena'          => $calc['first_quincena'],
                'second_quincena'         => $calc['second_quincena'],
                'cash_paid'               => $calc['cash_paid'],
                'processed_by'            => $processedBy,
                'deduction_snapshot'      => $calc['deduction_snapshot'],
                'salary_rate_snapshot'    => 0.0,
                'employee_code_snapshot'  => $empCode,
                'full_name_snapshot'      => $fullName,
                'office_id_snapshot'      => $officeId,
                'office_name_snapshot'    => $officeName,
                'position_snapshot'       => $empPos,
                'atm_account_no_snapshot' => $empAtm,
            ];

            $existingPayroll = $payrollModel->where('employee_id', $empId)
                                            ->where('payroll_period', $period)
                                            ->first();
            if ($existingPayroll) {
                $payrollModel->update($existingPayroll['id'], $payrollData);
            } else {
                $payrollData['employee_id']    = $empId;
                $payrollData['payroll_period'] = $period;
                $payrollModel->insert($payrollData);
            }
        }

        return ['imported' => $imported, 'updated' => $updated];
    }

    private function findHeaderColumn(array $rows, array $labels): ?int
    {
        for ($r = 0; $r <= 11 && $r < count($rows); $r++) {
            foreach ($rows[$r] as $c => $val) {
                $cell = preg_replace('/[^a-z0-9]/', '', strtolower(trim((string) $val)));
                if (in_array($cell, $labels, true)) {
                    return $c;
                }
            }
        }
        return null;
    }

    private function firstNonBlankInColumn(array $rows, int $start, int $end, int $col): float
    {
        for ($r = $start; $r < $end; $r++) {
            $raw = trim((string) ($rows[$r][$col] ?? ''));
            if ($raw !== '' && $raw !== '-') {
                $raw = str_replace([',', ' '], '', $raw);
                if (is_numeric($raw)) {
                    return (float) $raw;
                }
            }
        }
        return 0.0;
    }
}
