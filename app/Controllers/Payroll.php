<?php

namespace App\Controllers;

use App\Libraries\PayrollCalculator;
use App\Models\AppSettingModel;
use App\Models\DeductionModel;
use App\Models\EmployeeModel;
use App\Models\OfficeModel;
use App\Models\PayrollModel;
use App\Models\PayrollPeriodModel;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class Payroll extends BaseController
{
    public function index()
    {
        $officeId    = $this->request->getVar('office_id');
        $search      = trim((string) $this->request->getVar('search'));
        $period      = PayrollCalculator::sanitizePeriod($this->request->getVar('period'));
        $officeModel = new OfficeModel();
        $periodModel = new PayrollPeriodModel();

        $offices = $officeModel->getOfficesOrdered();

        if ($officeId === null && !empty($offices)) {
            foreach ($offices as $office) {
                if (stripos($office['office_name'], 'MAYOR') !== false) {
                    $officeId = $office['id'];
                    break;
                }
            }
        }

        $periodInfo = $periodModel->getPeriodInfo($period);
        $isLocked   = ($periodInfo['status'] ?? 'draft') === 'locked';
        $employees  = $this->fetchPeriodRows($period, $officeId, $search, $isLocked);

        return view('payroll/index', [
            'offices'        => $offices,
            'employees'      => $employees,
            'office_id'      => $officeId,
            'search'         => $search,
            'period'         => $period,
            'period_label'   => PayrollCalculator::formatPeriodLabel($period),
            'service_period' => PayrollCalculator::periodDateRange($period),
            'period_info'    => $periodInfo,
            'is_locked'      => $isLocked,
            'known_periods'  => $periodModel->listKnownPeriods(),
        ]);
    }

    public function printAll()
    {
        $officeId    = $this->request->getVar('office_id');
        $period      = PayrollCalculator::sanitizePeriod($this->request->getVar('period'));
        $officeModel = new OfficeModel();
        $periodModel = new PayrollPeriodModel();
        $settings    = (new AppSettingModel())->getAllSettings();

        $isLocked  = $periodModel->isLocked($period);
        $employees = $this->fetchPeriodRows($period, $officeId, '', $isLocked, 'employees.id', 'ASC');

        $grossTotal  = 0.0;
        $deductTotal = 0.0;
        $netTotal    = 0.0;
        foreach ($employees as $emp) {
            $grossTotal  += $emp['gross_pay_effective'];
            $deductTotal += $emp['total_deductions_effective'];
            $netTotal    += $emp['net_pay_effective'];
        }

        return view('payroll/print', [
            'employees'        => $employees,
            'office_id'        => $officeId,
            'offices'          => $officeModel->getOfficesOrdered(),
            'period'           => $period,
            'period_label'     => PayrollCalculator::formatPeriodLabel($period),
            'service_period'   => PayrollCalculator::periodDateRange($period),
            'total_gross'      => $grossTotal,
            'total_deductions' => $deductTotal,
            'total_net'        => $netTotal,
            'settings'         => $settings,
        ]);
    }

    public function process($employee_id)
    {
        $empModel     = new EmployeeModel();
        $deductModel  = new DeductionModel();
        $payrollModel = new PayrollModel();
        $periodModel  = new PayrollPeriodModel();
        $officeModel  = new OfficeModel();

        $period = PayrollCalculator::sanitizePeriod($this->request->getPost('period'));
        if ($periodModel->isLocked($period)) {
            return redirect()->to('/payroll?period=' . $period)->with('error', "Period {$period} is locked. Unlock it first to make changes.");
        }

        $emp = $empModel->find($employee_id);
        if (!$emp) {
            return redirect()->to('/payroll?period=' . $period)->with('error', 'Employee not found.');
        }

        $existing = $payrollModel->where('employee_id', $employee_id)
                                 ->where('payroll_period', $period)
                                 ->first();
        $deductRow = $deductModel->where('employee_id', $employee_id)->first() ?? [];

        $isPastUnlocked = ($period < date('Y-m'));
        $deductions = ($isPastUnlocked && $existing)
            ? PayrollCalculator::extractSnapshot($existing, $deductRow)
            : PayrollCalculator::normalizeDeductions($deductRow);

        $refundInput = $this->request->getPost('refund_rata');
        $refundRata  = ($refundInput !== null && $refundInput !== '')
            ? PayrollCalculator::toMoney($refundInput)
            : (float) ($existing['refund_rata'] ?? 0);

        $salaryRate = ($isPastUnlocked && $existing && $existing['salary_rate_snapshot'] !== null)
            ? (float) $existing['salary_rate_snapshot']
            : (float) ($emp['salary_rate'] ?? 0);

        $firstQInput = $this->request->getPost('first_quincena');
        $firstQ      = ($firstQInput !== null && $firstQInput !== '')
            ? PayrollCalculator::toMoney($firstQInput)
            : null;

        $calc = PayrollCalculator::computePayroll($salaryRate, $refundRata, $deductions, $firstQ, true);

        $officeId   = $emp['office_id'] ? (int) $emp['office_id'] : null;
        $officeName = $officeId ? (($officeModel->find($officeId))['office_name'] ?? null) : null;

        $payrollData = [
            'employee_id'             => (int) $employee_id,
            'payroll_period'          => $period,
            'period_of_service'       => $this->request->getPost('period_of_service') ?: PayrollCalculator::periodDateRange($period),
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
            'employee_code_snapshot'  => $emp['employee_id'],
            'full_name_snapshot'      => $emp['full_name'],
            'office_id_snapshot'      => $officeId,
            'office_name_snapshot'    => $officeName,
            'position_snapshot'       => $emp['position'] ?? '',
            'atm_account_no_snapshot' => $emp['atm_account_no'] ?? null,
        ];

        if ($existing) {
            $payrollModel->update($existing['id'], $payrollData);
        } else {
            $payrollModel->insert($payrollData);
        }

        $redirect = '/payroll?period=' . $period;
        if ($this->request->getPost('office_id')) {
            $redirect .= '&office_id=' . urlencode((string) $this->request->getPost('office_id'));
        }

        return redirect()->to($redirect)->with('success', 'Payroll calculated for ' . $emp['full_name'] . " ({$period}).");
    }

    public function processBatch()
    {
        $period   = PayrollCalculator::sanitizePeriod($this->request->getPost('period'));
        $officeId = $this->request->getPost('office_id');

        $periodModel  = new PayrollPeriodModel();
        $empModel     = new EmployeeModel();
        $deductModel  = new DeductionModel();
        $payrollModel = new PayrollModel();
        $officeModel  = new OfficeModel();

        if ($periodModel->isLocked($period)) {
            return redirect()->to('/payroll?period=' . $period)->with('error', "Period {$period} is locked. Unlock it first to process payroll.");
        }

        $officeMap = [];
        $rataIds   = [];
        foreach ($officeModel->findAll() as $o) {
            $officeMap[(int) $o['id']] = $o['office_name'];
            if (PayrollCalculator::isRataOfficeName($o['office_name'] ?? '')) {
                $rataIds[] = (int) $o['id'];
            }
        }

        $empQuery = $empModel->where('is_active', 1);
        if ($officeId) {
            $empQuery->where('office_id', $officeId);
        } elseif (!empty($rataIds)) {
            $empQuery->groupStart()
                     ->whereNotIn('office_id', $rataIds)
                     ->orWhere('office_id IS NULL')
                     ->groupEnd();
        }
        $employees = $empQuery->findAll();

        $processed   = 0;
        $skippedZero = 0;
        $processedBy = session()->get('id') ?: null;
        $serviceRange = PayrollCalculator::periodDateRange($period);

        $db = $payrollModel->db;
        $db->transBegin();
        try {
            foreach ($employees as $emp) {
                $salaryRate = (float) ($emp['salary_rate'] ?? 0);
                if ($salaryRate <= 0) {
                    $skippedZero++;
                    continue;
                }

                $empId    = (int) $emp['id'];
                $existing = $payrollModel->where('employee_id', $empId)
                                         ->where('payroll_period', $period)
                                         ->first();
                $deductRow  = $deductModel->where('employee_id', $empId)->first() ?? [];
                $deductions = PayrollCalculator::normalizeDeductions($deductRow);

                $refundRata = (float) ($existing['refund_rata'] ?? 0);
                $existingQ1 = ($existing && isset($existing['first_quincena']) && (float) $existing['first_quincena'] > 0)
                    ? (float) $existing['first_quincena']
                    : null;

                $calc = PayrollCalculator::computePayroll($salaryRate, $refundRata, $deductions, $existingQ1);
                $empOfficeId = $emp['office_id'] ? (int) $emp['office_id'] : null;

                $payrollData = [
                    'period_of_service'       => $existing['period_of_service'] ?? $serviceRange,
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
                    'employee_code_snapshot'  => $emp['employee_id'],
                    'full_name_snapshot'      => $emp['full_name'],
                    'office_id_snapshot'      => $empOfficeId,
                    'office_name_snapshot'    => $empOfficeId ? ($officeMap[$empOfficeId] ?? null) : null,
                    'position_snapshot'       => $emp['position'] ?? '',
                    'atm_account_no_snapshot' => $emp['atm_account_no'] ?? null,
                ];

                if ($existing) {
                    $payrollModel->update($existing['id'], $payrollData);
                } else {
                    $payrollData['employee_id']    = $empId;
                    $payrollData['payroll_period'] = $period;
                    $payrollModel->insert($payrollData);
                }
                $processed++;
            }

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Database error during batch payroll processing.');
            }
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->to('/payroll?period=' . $period)->with('error', 'Batch processing failed: ' . $e->getMessage());
        }

        $label = PayrollCalculator::formatPeriodLabel($period);
        $msg   = "Processed {$processed} employee(s) for {$label}.";
        if ($skippedZero > 0) {
            $msg .= " Skipped {$skippedZero} with ₱0 salary rate.";
        }

        $redirect = '/payroll?period=' . $period . ($officeId ? '&office_id=' . urlencode((string) $officeId) : '');
        return redirect()->to($redirect)->with('success', $msg);
    }

    public function lock()
    {
        $period   = PayrollCalculator::sanitizePeriod($this->request->getPost('period'));
        $officeId = $this->request->getPost('office_id');
        $notes    = trim((string) $this->request->getPost('notes')) ?: null;

        (new PayrollPeriodModel())->lock($period, session()->get('id') ?: null, $notes);

        $redirect = '/payroll?period=' . $period . ($officeId ? '&office_id=' . urlencode((string) $officeId) : '');
        return redirect()->to($redirect)->with('success', "Period {$period} is now LOCKED. Closed payroll records are protected from edits.");
    }

    public function unlock()
    {
        $period   = PayrollCalculator::sanitizePeriod($this->request->getPost('period'));
        $officeId = $this->request->getPost('office_id');

        (new PayrollPeriodModel())->unlock($period);

        $redirect = '/payroll?period=' . $period . ($officeId ? '&office_id=' . urlencode((string) $officeId) : '');
        return redirect()->to($redirect)->with('success', "Period {$period} unlocked and returned to DRAFT mode.");
    }

    public function export()
    {
        $officeId    = $this->request->getVar('office_id');
        $period      = PayrollCalculator::sanitizePeriod($this->request->getVar('period'));
        $officeModel = new OfficeModel();
        $periodModel = new PayrollPeriodModel();
        $settings    = (new AppSettingModel())->getAllSettings();
        $isLocked    = $periodModel->isLocked($period);

        $offices = $officeModel->getOfficesOrdered();
        if ($officeId) {
            $offices = array_values(array_filter($offices, fn ($o) => (string) $o['id'] === (string) $officeId));
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        $sheetIndex = 0;
        foreach ($offices as $office) {
            $employees = $this->fetchPeriodRows($period, (string) $office['id'], '', $isLocked, 'full_name', 'ASC');
            if (empty($employees)) {
                continue;
            }

            $sheet = $spreadsheet->createSheet($sheetIndex++);
            $sheet->setTitle($this->safeSheetTitle($office['office_name']));
            $this->writeOfficeSheet($sheet, $office['office_name'], $employees, $period, $settings);
        }

        if ($sheetIndex === 0) {
            $sheet = $spreadsheet->createSheet(0);
            $sheet->setTitle('Payroll');
            $sheet->setCellValue('A1', 'No employees with payroll data for ' . PayrollCalculator::formatPeriodLabel($period) . '.');
        }

        $spreadsheet->setActiveSheetIndex(0);
        $writer   = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $filename = 'municipal_payroll_' . $period
            . ($officeId && !empty($offices) ? '_' . preg_replace('/[^a-zA-Z0-9]/', '', $offices[0]['office_name']) : '')
            . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        header('Expires: 0');

        $writer->save('php://output');
        exit;
    }

    /**
     * Shared period-aware payroll loader:
     * - Locked periods render strictly from payroll_records snapshots (immune to later hires/transfers/edits).
     * - Draft periods render active employees plus any employee with an existing record for that period.
     */
    private function fetchPeriodRows(
        string $period,
        ?string $officeId = null,
        string $search = '',
        bool $isLocked = false,
        string $orderBy = 'employees.id',
        string $orderDir = 'ASC'
    ): array {
        $db = \Config\Database::connect();

        if ($isLocked) {
            $builder = $db->table('payroll_records pr')
                ->select('pr.id as payroll_id, pr.employee_id as id, pr.payroll_period, pr.period_of_service,
                          pr.refund_rata, pr.gross_pay, pr.total_deductions, pr.net_pay,
                          pr.first_quincena, pr.second_quincena, pr.cash_paid,
                          pr.deduction_snapshot, pr.salary_rate_snapshot,
                          pr.employee_code_snapshot, pr.full_name_snapshot,
                          pr.office_id_snapshot, pr.office_name_snapshot,
                          pr.position_snapshot, pr.atm_account_no_snapshot,
                          e.employee_id as live_employee_code, e.full_name as live_full_name,
                          e.position as live_position, e.salary_rate as live_salary_rate,
                          e.office_id as live_office_id, e.atm_account_no as live_atm_account_no,
                          e.contact_number, o.office_name as live_office_name,
                          d.gsis_premium, d.gsis_policy, d.gsis_other, d.gsis_ouli, d.gsis_diff,
                          d.pagibig_premium, d.pagibig_loan, d.pagibig_mp2, d.phic, d.phic_diff,
                          d.withholding_tax, d.loans, d.government_cont, d.other_deduct,
                          d.bank_lbp, d.bank_other_payables, d.bank_mcc, d.bank_1stvb, d.bank_rbt')
                ->join('employees e', 'e.id = pr.employee_id', 'left')
                ->join('offices o', 'o.id = COALESCE(pr.office_id_snapshot, e.office_id)', 'left')
                ->join('deductions d', 'd.employee_id = pr.employee_id', 'left')
                ->where('pr.payroll_period', $period);

            if ($officeId) {
                $builder->where('COALESCE(pr.office_id_snapshot, e.office_id) =', (int) $officeId, false);
            }
            if ($search !== '') {
                $builder->groupStart()
                        ->like('COALESCE(pr.full_name_snapshot, e.full_name)', $search)
                        ->orLike('COALESCE(pr.employee_code_snapshot, e.employee_id)', $search)
                        ->orLike('COALESCE(pr.atm_account_no_snapshot, e.atm_account_no)', $search)
                        ->groupEnd();
            }

            $rows = $builder->orderBy($orderBy === 'full_name' ? 'COALESCE(pr.full_name_snapshot, e.full_name)' : 'pr.employee_id', $orderDir, false)
                            ->get()->getResultArray();
        } else {
            $builder = $db->table('employees e')
                ->select('e.id, e.employee_id as live_employee_code, e.full_name as live_full_name,
                          e.position as live_position, e.salary_rate as live_salary_rate,
                          e.office_id as live_office_id, e.atm_account_no as live_atm_account_no,
                          e.contact_number, e.is_active,
                          o.office_name as live_office_name,
                          d.gsis_premium, d.gsis_policy, d.gsis_other, d.gsis_ouli, d.gsis_diff,
                          d.pagibig_premium, d.pagibig_loan, d.pagibig_mp2, d.phic, d.phic_diff,
                          d.withholding_tax, d.loans, d.government_cont, d.other_deduct,
                          d.bank_lbp, d.bank_other_payables, d.bank_mcc, d.bank_1stvb, d.bank_rbt,
                          pr.id as payroll_id, pr.payroll_period, pr.period_of_service,
                          pr.refund_rata, pr.gross_pay, pr.total_deductions, pr.net_pay,
                          pr.first_quincena, pr.second_quincena, pr.cash_paid,
                          pr.deduction_snapshot, pr.salary_rate_snapshot,
                          pr.employee_code_snapshot, pr.full_name_snapshot,
                          pr.office_id_snapshot, pr.office_name_snapshot,
                          pr.position_snapshot, pr.atm_account_no_snapshot')
                ->join('offices o', 'o.id = e.office_id', 'left')
                ->join('deductions d', 'd.employee_id = e.id', 'left')
                ->join('payroll_records pr', 'pr.employee_id = e.id AND pr.payroll_period = ' . $db->escape($period), 'left')
                ->groupStart()
                    ->where('e.is_active', 1)
                    ->orWhere('pr.id IS NOT NULL', null, false)
                ->groupEnd();

            if ($officeId) {
                $builder->where('e.office_id', (int) $officeId);
            }
            if ($search !== '') {
                $builder->groupStart()
                        ->like('e.full_name', $search)
                        ->orLike('e.employee_id', $search)
                        ->orLike('e.atm_account_no', $search)
                        ->groupEnd();
            }

            $rows = $builder->orderBy($orderBy === 'full_name' ? 'e.full_name' : 'e.id', $orderDir)
                            ->get()->getResultArray();
        }

        $result = [];
        foreach ($rows as $row) {
            $hasPayroll = !empty($row['payroll_id']);
            $deductions = $hasPayroll
                ? PayrollCalculator::extractSnapshot($row)
                : PayrollCalculator::normalizeDeductions($row);

            $salaryRate = ($hasPayroll && $row['salary_rate_snapshot'] !== null)
                ? (float) $row['salary_rate_snapshot']
                : (float) ($row['live_salary_rate'] ?? 0);
            $refundRata = (float) ($row['refund_rata'] ?? 0);
            $existingQ1 = ($hasPayroll && $row['first_quincena'] !== null && (float) $row['first_quincena'] > 0)
                ? (float) $row['first_quincena']
                : null;

            $calc = PayrollCalculator::computePayroll($salaryRate, $refundRata, $deductions, $existingQ1, true);

            $fullName   = ($isLocked && !empty($row['full_name_snapshot'])) ? $row['full_name_snapshot'] : ($row['live_full_name'] ?? $row['full_name_snapshot'] ?? '');
            $position   = ($isLocked && !empty($row['position_snapshot'])) ? $row['position_snapshot'] : ($row['live_position'] ?? $row['position_snapshot'] ?? '');
            $officeName = ($isLocked && !empty($row['office_name_snapshot'])) ? $row['office_name_snapshot'] : ($row['live_office_name'] ?? $row['office_name_snapshot'] ?? '');
            $empCode    = ($isLocked && !empty($row['employee_code_snapshot'])) ? $row['employee_code_snapshot'] : ($row['live_employee_code'] ?? $row['employee_code_snapshot'] ?? '');
            $atmNo      = ($isLocked && $row['atm_account_no_snapshot'] !== null) ? $row['atm_account_no_snapshot'] : ($row['live_atm_account_no'] ?? $row['atm_account_no_snapshot'] ?? '');

            $row['full_name']                  = $fullName;
            $row['position']                   = $position;
            $row['office_name']                = $officeName;
            $row['employee_id']                = $empCode;
            $row['atm_account_no']             = $atmNo;
            $row['salary_rate']                = $salaryRate;
            $row['refund_rata']                = $refundRata;
            $row['deductions_effective']       = $deductions;
            $row['groups']                     = PayrollCalculator::groupTotals($deductions);
            $row['gross_pay_effective']        = $calc['gross_pay'];
            $row['total_deductions_effective'] = $calc['total_deductions'];
            $row['net_pay_effective']          = $calc['net_pay'];
            $row['first_quincena']             = $calc['first_quincena'];
            $row['second_quincena']            = $calc['second_quincena'];
            $row['signature_display']          = PayrollCalculator::signatureValue([
                'atm_account_no' => $atmNo,
                'contact_number' => $row['contact_number'] ?? '',
            ]);
            $result[] = $row;
        }

        return $result;
    }

    private function safeSheetTitle(string $officeName): string
    {
        $title = preg_replace('/[:\\\\\/\?\*\[\]]/', '', $officeName);
        return mb_substr($title, 0, 31);
    }

    private function writeOfficeSheet(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        string $officeName,
        array $employees,
        string $period,
        array $settings
    ): void {
        $arial       = 'Arial';
        $currencyFmt = '_(* #,##0.00_);_(* (#,##0.00);_(* -??_);_(@_)';
        $thin        = ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']];

        $lguName        = $settings['lgu_name'] ?? 'LGU-MAHINOG';
        $municipality   = $settings['municipality_label'] ?? 'Mahinog, Camiguin';
        $mayorName      = $settings['mayor_name'] ?? 'REY LAWRENCE K. TAN';
        $mayorTitle     = $settings['mayor_title'] ?? 'Municipal Mayor';
        $treasurerName  = $settings['treasurer_name'] ?? 'MARY LUSSEL S. PACTO';
        $treasurerTitle = $settings['treasurer_title'] ?? 'Disbursing Officer';
        $serviceRange   = PayrollCalculator::periodDateRange($period);

        $sheet->getPageSetup()
            ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_LEGAL);

        $widths = [
            'A' => 4, 'B' => 22, 'C' => 16, 'D' => 10, 'E' => 10, 'F' => 10,
            'G' => 10, 'H' => 10, 'I' => 10, 'J' => 10, 'K' => 10, 'L' => 10,
            'M' => 10, 'N' => 10, 'O' => 10, 'P' => 10, 'Q' => 11, 'R' => 11,
            'S' => 12, 'T' => 13,
        ];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        $sheet->setCellValue('A1', $lguName);
        $sheet->mergeCells('A1:T1');
        $sheet->setCellValue('A2', 'MUNICIPAL PAYROLL');
        $sheet->mergeCells('A2:T2');
        foreach (['A1', 'A2'] as $c) {
            $sheet->getStyle($c)->getFont()->setName($arial)->setSize(10)->setBold(true);
            $sheet->getStyle($c)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        $ack = sprintf(
            '           We hereby acknowledge to have received from %s. Treasurer of %s the sums herein specified opposite our respective names, the same, being full compensation for our services rendered during the period stated below, to the correctness of which we hereby severally certify.',
            $treasurerName,
            $municipality
        );
        $sheet->setCellValue('A4', $ack);
        $sheet->mergeCells('A4:T4');
        $sheet->getStyle('A4')->getFont()->setName($arial)->setSize(9);
        $sheet->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getRowDimension(4)->setRowHeight(30);

        $headerCells = [
            'A6' => 'No.', 'B6' => 'NAME', 'C6' => 'DESIGNATION',
            'G6' => 'DEDUCTIONS', 'Q6' => 'NET PAY',
            'S6' => 'Amount Paid in Cash', 'T6' => 'SIGNATURE',
            'F7' => 'REFUND', 'G7' => 'GSIS', 'J7' => 'PAG-IBIG',
            'L7' => 'PHIC', 'M7' => "BANK's / COOP's", 'P7' => 'BIR W/T Tax',
            'D8' => 'PERIOD ', 'E8' => 'MONTHLY', 'F8' => 'RATA',
            'G8' => 'PREMIUM', 'H8' => 'CONSO', 'I8' => 'GFAL',
            'J8' => 'PREMIUM', 'K8' => 'SALARY', 'L8' => 'PHIC',
            'M8' => 'LBP', 'N8' => 'MCC', 'O8' => '1stVB', 'R8' => '1st quincena',
            'D9' => 'OF', 'E9' => 'RATE OF', 'F9' => 'PERA  ACA',
            'G9' => '(Personal)', 'H9' => 'Policy', 'I9' => 'EMRGYLN',
            'J9' => '(Personal)', 'K9' => 'LOAN', 'M9' => 'Other Payables',
            'O9' => 'RBT', 'R9' => '2nd quincena',
            'D10' => 'SERVICE', 'E10' => 'PAY', 'G10' => 'OULI',
            'H10' => 'MPL', 'I10' => 'MPL LITE', 'K10' => 'MP2',
            'F11' => 'DIFFERENTIAL', 'G11' => 'DIFF-GSIS', 'I11' => 'CPL', 'L11' => 'PHIC-Diff',
        ];
        foreach ($headerCells as $coord => $val) {
            $sheet->setCellValue($coord, $val);
        }
        $sheet->mergeCells('A6:A11');
        $sheet->mergeCells('B6:B11');
        $sheet->mergeCells('C6:C11');
        $sheet->mergeCells('G6:P6');
        $sheet->mergeCells('Q6:Q11');
        $sheet->mergeCells('S6:S11');
        $sheet->mergeCells('T6:T11');
        $sheet->mergeCells('G7:I7');
        $sheet->mergeCells('J7:K7');
        $sheet->mergeCells('L7:L11');
        $sheet->mergeCells('M7:O7');
        $sheet->mergeCells('P7:P11');
        $sheet->mergeCells('F8:F10');

        $sheet->getStyle('A6:T11')->getFont()->setName($arial)->setSize(8)->setBold(true);
        $sheet->getStyle('A6:T11')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        $sheet->getStyle('A6:T11')->getBorders()->getAllBorders()->applyFromArray($thin);

        $row = 12;
        $colNumbers = ['A'=>1,'B'=>2,'C'=>3,'D'=>4,'E'=>5,'F'=>6,'G'=>7,'H'=>8,'I'=>9,'J'=>10,
                       'K'=>11,'L'=>12,'M'=>14,'N'=>16,'O'=>17,'P'=>19,'Q'=>20,'R'=>21,'S'=>22,'T'=>23];
        foreach ($colNumbers as $col => $n) {
            $sheet->setCellValue($col . $row, $n);
        }
        $sheet->getStyle('A12:T12')->getFont()->setName($arial)->setSize(8)->setBold(true);
        $sheet->getStyle('A12:T12')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A12:T12')->getBorders()->getAllBorders()->applyFromArray($thin);

        $startRow     = 13;
        $r            = $startRow;
        $quincenaRefs = [];
        $no           = 1;

        foreach ($employees as $emp) {
            $d  = $emp['deductions_effective'];
            $g  = $emp['groups'];
            $r1 = $r;
            $r2 = $r + 1;
            $r3 = $r + 2;
            $r4 = $r + 3;

            $sheet->setCellValue("A{$r1}", $no++);
            $sheet->setCellValue("B{$r1}", $emp['full_name']);
            $sheet->setCellValue("C{$r1}", $emp['position']);
            $sheet->setCellValue("D{$r1}", $emp['period_of_service'] ?? $serviceRange);
            $sheet->setCellValue("E{$r1}", (float) $emp['salary_rate']);
            $sheet->setCellValue("F{$r2}", (float) ($emp['refund_rata'] ?? 0));

            $sheet->setCellValue("G{$r1}", (float) $d['gsis_premium']);
            $sheet->setCellValue("G{$r3}", (float) $d['gsis_ouli']);
            $sheet->setCellValue("G{$r4}", (float) $d['gsis_diff']);
            $sheet->setCellValue("H{$r1}", (float) $d['gsis_policy']);
            $sheet->setCellValue("I{$r1}", (float) $d['gsis_other']);

            $sheet->setCellValue("J{$r1}", (float) $d['pagibig_premium']);
            $sheet->setCellValue("K{$r1}", (float) $d['pagibig_loan']);
            $sheet->setCellValue("K{$r3}", (float) $d['pagibig_mp2']);

            $sheet->setCellValue("L{$r1}", (float) $d['phic']);
            $sheet->setCellValue("L{$r4}", (float) $d['phic_diff']);

            $sheet->setCellValue("M{$r1}", (float) $d['bank_lbp']);
            $sheet->setCellValue("M{$r3}", (float) $d['bank_other_payables']);
            // Fold legacy loans + government_cont + other_deduct into M sub-row 4 so Excel SUM matches DB net_pay
            if ($g['other'] != 0.0) {
                $sheet->setCellValue("M{$r4}", (float) $g['other']);
            }
            $sheet->setCellValue("N{$r1}", (float) $d['bank_mcc']);
            $sheet->setCellValue("O{$r1}", (float) $d['bank_1stvb']);
            $sheet->setCellValue("O{$r3}", (float) $d['bank_rbt']);

            $sheet->setCellValue("P{$r1}", (float) $d['withholding_tax']);

            $sheet->setCellValue("Q{$r1}", "=SUM(E{$r1}:F{$r4})-SUM(G{$r1}:P{$r4})");
            $sheet->mergeCells("Q{$r1}:Q{$r4}");

            $sheet->setCellValue("R{$r1}", (float) ($emp['first_quincena'] ?? 0) ?: "=ROUND(Q{$r1}/2,2)");
            $sheet->setCellValue("R{$r2}", (float) ($emp['second_quincena'] ?? 0) ?: "=Q{$r1}-R{$r1}");
            $quincenaRefs[] = ["R{$r1}", "R{$r2}", "Q{$r1}"];

            $sheet->setCellValue("T{$r1}", $emp['signature_display'] ?? '');

            foreach (['A', 'B', 'C', 'D', 'E'] as $col) {
                $sheet->mergeCells("{$col}{$r1}:{$col}{$r4}");
            }
            $sheet->mergeCells("S{$r1}:S{$r4}");
            $sheet->mergeCells("T{$r1}:T{$r4}");

            $sheet->getStyle("A{$r1}:T{$r4}")->getFont()->setName($arial)->setSize(8);
            $sheet->getStyle("A{$r1}:C{$r4}")->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
            $sheet->getStyle("D{$r1}:T{$r4}")->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("E{$r1}:R{$r4}")->getNumberFormat()->setFormatCode($currencyFmt);
            foreach (['A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T'] as $col) {
                $sheet->getStyle("{$col}{$r1}:{$col}{$r4}")->getBorders()->getOutline()->applyFromArray($thin);
            }

            $r = $r4 + 1;
        }

        $lastEmployeeRow = $r - 1;
        $q1Row    = $r++;
        $q2Row    = $r++;
        $totalRow = $r++;

        $sheet->setCellValue("Q{$q1Row}", '1st Quincena');
        $sheet->setCellValue("R{$q1Row}", '=' . implode('+', array_column($quincenaRefs, 0)));
        $sheet->setCellValue("Q{$q2Row}", '2nd Quincena');
        $sheet->setCellValue("R{$q2Row}", '=' . implode('+', array_column($quincenaRefs, 1)));

        $sheet->setCellValue("A{$totalRow}", 'Total');
        foreach (['E','F','G','H','I','J','K','L','M','N','O','P'] as $col) {
            $sheet->setCellValue("{$col}{$totalRow}", "=SUM({$col}{$startRow}:{$col}{$lastEmployeeRow})");
        }
        $sheet->setCellValue("Q{$totalRow}", '=' . implode('+', array_column($quincenaRefs, 2)));
        $sheet->setCellValue("R{$totalRow}", "=R{$q1Row}+R{$q2Row}");

        $sheet->getStyle("A{$totalRow}")->getFont()->setBold(true);
        $sheet->getStyle("Q{$q1Row}:R{$totalRow}")->getFont()->setName($arial)->setSize(8)->setBold(true);
        $sheet->getStyle("E{$totalRow}:R{$totalRow}")->getFont()->setBold(true);
        $sheet->getStyle("E{$totalRow}:R{$totalRow}")->getNumberFormat()->setFormatCode($currencyFmt);
        $sheet->getStyle("A{$totalRow}:T{$totalRow}")->getBorders()->getAllBorders()->applyFromArray($thin);

        $footerRow = $totalRow + 2;
        $sheet->setCellValue("B{$footerRow}", '(1)  I HEREBY CERTIFY on my official oath that the above PAYROLL is correct, and that services above stated have been duly rendered.  Payment for such services is also hereby approved from the appropriation indicated.');
        $sheet->setCellValue("J{$footerRow}", '(4) APPROVED:');
        $sheet->setCellValue("M{$footerRow}", '(5) I HEREBY CERTIFY on my official oath that I have paid in cash to each official and employee whose name appears on the above roll the amount set opposite his name, under column 17, the having signed or marked his name under column 20 above, in my presence and at the time that payment was made to him in acknowledgement of receipt of the money paid him.');
        $sheet->mergeCells("B{$footerRow}:I{$footerRow}");
        $sheet->mergeCells("M{$footerRow}:T{$footerRow}");
        $sheet->getStyle("B{$footerRow}:T{$footerRow}")->getFont()->setName($arial)->setSize(8);
        $sheet->getStyle("B{$footerRow}:T{$footerRow}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getRowDimension($footerRow)->setRowHeight(45);

        $signRow1 = $footerRow + 1;
        $signRow2 = $footerRow + 2;
        $sheet->setCellValue("A{$signRow1}", 'Date: ' . date('F d, Y'));
        $sheet->setCellValue("C{$signRow1}", $mayorName);
        $sheet->setCellValue("J{$signRow1}", $mayorName);
        $sheet->setCellValue("Q{$signRow1}", $treasurerName);
        $sheet->setCellValue("C{$signRow2}", $mayorTitle);
        $sheet->setCellValue("J{$signRow2}", $mayorTitle);
        $sheet->setCellValue("Q{$signRow2}", $treasurerTitle);

        $sheet->getStyle("A{$signRow1}:T{$signRow2}")->getFont()->setName($arial)->setSize(8)->setBold(true);
        $sheet->getStyle("C{$signRow1}:C{$signRow2}")->getBorders()->getTop()->applyFromArray($thin);
        $sheet->getStyle("J{$signRow1}:K{$signRow2}")->getBorders()->getTop()->applyFromArray($thin);
        $sheet->getStyle("Q{$signRow1}:S{$signRow2}")->getBorders()->getTop()->applyFromArray($thin);
        $sheet->getStyle("C{$signRow1}:T{$signRow2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }
}
