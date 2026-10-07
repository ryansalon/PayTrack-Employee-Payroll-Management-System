<?php

namespace App\Controllers;

use App\Models\PayrollModel;
use App\Models\OfficeModel;

class Report extends BaseController
{
    public function index()
    {
        $officeModel = new OfficeModel();
        $payrollModel = new PayrollModel();

        $data['offices'] = $officeModel->getOfficesOrdered();
        $data['period'] = date('Y-m');

        $summary = $payrollModel->getPayrollSummary($data['period']);
        $data['total_gross'] = $summary['gross_pay'] ?? 0;
        $data['total_deductions'] = $summary['total_deductions'] ?? 0;
        $data['total_net'] = $summary['net_pay'] ?? 0;
        $data['total_employees'] = $payrollModel->where('payroll_period', $data['period'])->countAllResults();

        return view('report/index', $data);
    }

    public function generate()
    {
        $payrollModel = new PayrollModel();
        $officeModel = new OfficeModel();

        $type     = $this->request->getVar('report_type') ?? 'period';
        $period   = $this->request->getVar('period') ?? date('Y-m');
        $quarter  = $this->request->getVar('quarter') ?? 'Q1';
        $year     = $this->request->getVar('year') ?? date('Y');
        $officeId = $this->request->getVar('office_id') ?? 'all';

        $data['report_type'] = $type;
        $data['period']      = $period;
        $data['quarter']     = $quarter;
        $data['year']        = $year;
        $data['office_id']   = $officeId;

        if ($type === 'quarterly') {
            $quarterMonths = [
                'Q1' => ["{$year}-01", "{$year}-02", "{$year}-03"],
                'Q2' => ["{$year}-04", "{$year}-05", "{$year}-06"],
                'Q3' => ["{$year}-07", "{$year}-08", "{$year}-09"],
                'Q4' => ["{$year}-10", "{$year}-11", "{$year}-12"],
            ];
            $periods = $quarterMonths[$quarter] ?? $quarterMonths['Q1'];

            $query = $payrollModel->select('payroll_records.*, employees.full_name, employees.position, employees.employee_id as emp_code, offices.office_name, deductions.*')
                ->join('employees', 'employees.id = payroll_records.employee_id')
                ->join('offices', 'offices.id = employees.office_id')
                ->join('deductions', 'deductions.employee_id = employees.id', 'left')
                ->whereIn('payroll_records.payroll_period', $periods);

            if ($officeId !== 'all') {
                $query->where('employees.office_id', $officeId);
                $office = $officeModel->find($officeId);
                $data['office_name'] = $office['office_name'] ?? 'Unknown';
            } else {
                $data['office_name'] = 'All Offices';
            }

            $data['results']      = $query->findAll();
            $data['report_title'] = "Quarterly Payroll Summary ({$quarter} {$year})";

        } elseif ($type === 'annual') {
            $periods = [];
            for ($m = 1; $m <= 12; $m++) {
                $periods[] = sprintf("%s-%02d", $year, $m);
            }

            $query = $payrollModel->select('payroll_records.*, employees.full_name, employees.position, employees.employee_id as emp_code, offices.office_name, deductions.*')
                ->join('employees', 'employees.id = payroll_records.employee_id')
                ->join('offices', 'offices.id = employees.office_id')
                ->join('deductions', 'deductions.employee_id = employees.id', 'left')
                ->whereIn('payroll_records.payroll_period', $periods);

            if ($officeId !== 'all') {
                $query->where('employees.office_id', $officeId);
                $office = $officeModel->find($officeId);
                $data['office_name'] = $office['office_name'] ?? 'Unknown';
            } else {
                $data['office_name'] = 'All Offices';
            }

            $data['results']      = $query->findAll();
            $data['report_title'] = "Annual Payroll Summary ({$year})";

        } elseif ($type === 'office') {
            $data['results'] = [];
            $offices = $officeModel->getOfficesOrdered();
            foreach ($offices as $office) {
                $officeData = $payrollModel->select('payroll_records.*, employees.full_name, employees.position, offices.office_name')
                    ->join('employees', 'employees.id = payroll_records.employee_id')
                    ->join('offices', 'offices.id = employees.office_id')
                    ->where('employees.office_id', $office['id'])
                    ->where('payroll_records.payroll_period', $period)
                    ->findAll();

                if (!empty($officeData)) {
                    $totalGross = 0;
                    $totalDeduct = 0;
                    $totalNet = 0;
                    foreach ($officeData as $row) {
                        $totalGross += $row['gross_pay'];
                        $totalDeduct += $row['total_deductions'];
                        $totalNet += $row['net_pay'];
                    }
                    $data['results'][] = [
                        'office_name' => $office['office_name'],
                        'employees' => $officeData,
                        'total_gross' => $totalGross,
                        'total_deductions' => $totalDeduct,
                        'total_net' => $totalNet,
                        'count' => count($officeData),
                    ];
                }
            }
            $data['office_name']  = 'All Offices';
            $data['report_title'] = "Office-wise Payroll Summary (" . date('F Y', strtotime($period . '-01')) . ")";
        } elseif ($type === 'deductions') {
            $query = $payrollModel->select('payroll_records.*, employees.full_name, offices.office_name, deductions.*')
                ->join('employees', 'employees.id = payroll_records.employee_id')
                ->join('offices', 'offices.id = employees.office_id')
                ->join('deductions', 'deductions.employee_id = employees.id', 'left')
                ->where('payroll_records.payroll_period', $period);

            if ($officeId !== 'all') {
                $query->where('employees.office_id', $officeId);
                $office = $officeModel->find($officeId);
                $data['office_name'] = $office['office_name'] ?? 'Unknown';
            } else {
                $data['office_name'] = 'All Offices';
            }

            $data['results']      = $query->findAll();
            $data['report_title'] = "Deduction Analysis (" . date('F Y', strtotime($period . '-01')) . ")";
        } else {
            $query = $payrollModel->select('payroll_records.*, employees.full_name, employees.position, offices.office_name')
                ->join('employees', 'employees.id = payroll_records.employee_id')
                ->join('offices', 'offices.id = employees.office_id')
                ->where('payroll_records.payroll_period', $period);

            if ($officeId !== 'all') {
                $query->where('employees.office_id', $officeId);
                $office = $officeModel->find($officeId);
                $data['office_name'] = $office['office_name'] ?? 'Unknown';
            } else {
                $data['office_name'] = 'All Offices';
            }

            $data['results']      = $query->findAll();
            $data['report_title'] = "Monthly Payroll Record (" . date('F Y', strtotime($period . '-01')) . ")";
        }

        return view('report/summary', $data);
    }
}