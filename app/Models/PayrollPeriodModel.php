<?php

namespace App\Models;

use App\Libraries\PayrollCalculator;
use CodeIgniter\Model;

class PayrollPeriodModel extends Model
{
    protected $table            = 'payroll_periods';
    protected $primaryKey       = 'period';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $allowedFields    = ['period', 'status', 'locked_at', 'locked_by', 'notes', 'created_at', 'updated_at'];

    public function getPeriodInfo(string $period): array
    {
        $period = PayrollCalculator::sanitizePeriod($period);
        $row = $this->db->tableExists($this->table) ? $this->find($period) : null;
        return $row ?? [
            'period'    => $period,
            'status'    => 'draft',
            'locked_at' => null,
            'locked_by' => null,
            'notes'     => null,
        ];
    }

    public function isLocked(string $period): bool
    {
        return ($this->getPeriodInfo($period)['status'] ?? 'draft') === 'locked';
    }

    public function lock(string $period, ?int $userId = null, ?string $notes = null): void
    {
        $period = PayrollCalculator::sanitizePeriod($period);
        $now = date('Y-m-d H:i:s');
        $data = [
            'status'     => 'locked',
            'locked_at'  => $now,
            'locked_by'  => $userId,
            'notes'      => $notes,
            'updated_at' => $now,
        ];
        if ($this->find($period)) {
            $this->update($period, $data);
        } else {
            $data['period'] = $period;
            $data['created_at'] = $now;
            $this->insert($data);
        }
    }

    public function unlock(string $period): void
    {
        $period = PayrollCalculator::sanitizePeriod($period);
        $now = date('Y-m-d H:i:s');
        if ($this->find($period)) {
            $this->update($period, [
                'status'     => 'draft',
                'locked_at'  => null,
                'locked_by'  => null,
                'updated_at' => $now,
            ]);
        }
    }

    public function listKnownPeriods(): array
    {
        $periods = [date('Y-m')];
        if ($this->db->tableExists('payroll_records')) {
            $rows = $this->db->table('payroll_records')->select('payroll_period')->distinct()->get()->getResultArray();
            foreach ($rows as $r) {
                if (!empty($r['payroll_period'])) {
                    $periods[] = $r['payroll_period'];
                }
            }
        }
        if ($this->db->tableExists($this->table)) {
            foreach ($this->findAll() as $r) {
                $periods[] = $r['period'];
            }
        }
        $periods = array_values(array_unique($periods));
        rsort($periods);
        return $periods;
    }
}
