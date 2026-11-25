<?php

namespace App\Models;

use CodeIgniter\Model;

class EmployeeModel extends Model
{
    protected $table            = 'employees';
    protected $primaryKey       = 'id'; // Internal ID
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
protected $allowedFields    = [
        'employee_id',
        'full_name', 
        'office_id', 
        'position', 
        'contact_number',
        'salary_rate', 
        'employment_status', 
        'is_active',
        'atm_account_no'
    ];

    protected $validationRules = [
        'id'          => 'permit_empty|is_natural_no_zero',
        'employee_id' => 'required|is_unique[employees.employee_id,id,{id}]',
        'full_name'   => 'required',
        'position'    => 'required',
        'office_id'   => 'required|numeric',
    ];

    /**
     * Generates the next sequential employee code (e.g. EMP-2026-005) using the
     * maximum existing numeric suffix rather than count()+1 so deletions never collide.
     */
    public function nextCode(string $prefix, ?string $year = null): string
    {
        $year = $year ?? date('Y');
        $base = "{$prefix}-{$year}-";
        $rows = $this->select('employee_id')->like('employee_id', $base, 'after')->findAll();
        $max = 0;
        foreach ($rows as $row) {
            $suffix = substr((string) $row['employee_id'], strlen($base));
            if (ctype_digit($suffix)) {
                $max = max($max, (int) $suffix);
            }
        }
        return $base . str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
    }

    /**
     * Search functionality as described in the requirements
     */
    public function searchEmployees($keyword)
    {
        return $this->table($this->table)
                    ->like('employee_id', $keyword)
                    ->orLike('full_name', $keyword)
                    ->findAll();
    }
}