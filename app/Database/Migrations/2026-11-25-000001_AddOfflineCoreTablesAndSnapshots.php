<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddOfflineCoreTablesAndSnapshots extends Migration
{
    public function up()
    {
        // 1. LGU & System Settings
        $this->forge->addField([
            'setting_key' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'setting_value' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('setting_key', true);
        $this->forge->createTable('app_settings', true);

        $defaults = [
            'lgu_name'           => 'LGU-MAHINOG',
            'municipality_label' => 'Mahinog, Camiguin',
            'mayor_name'         => 'REY LAWRENCE K. TAN',
            'mayor_title'        => 'Municipal Mayor',
            'treasurer_name'     => 'MARY LUSSEL S. PACTO',
            'treasurer_title'    => 'Disbursing Officer',
        ];
        $now = date('Y-m-d H:i:s');
        foreach ($defaults as $key => $val) {
            $exists = $this->db->table('app_settings')->where('setting_key', $key)->countAllResults();
            if ($exists === 0) {
                $this->db->table('app_settings')->insert([
                    'setting_key'   => $key,
                    'setting_value' => $val,
                    'updated_at'    => $now,
                ]);
            }
        }

        // 2. Payroll Periods (draft / locked lifecycle)
        $this->forge->addField([
            'period' => [
                'type'       => 'VARCHAR',
                'constraint' => 7,
            ],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'draft',
            ],
            'locked_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'locked_by' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'notes' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('period', true);
        $this->forge->createTable('payroll_periods', true);

        // 3. Additive snapshot columns on payroll_records
        $snapshotFields = [
            'deduction_snapshot'      => ['type' => 'TEXT', 'null' => true],
            'salary_rate_snapshot'    => ['type' => 'DECIMAL', 'constraint' => '12,2', 'null' => true],
            'employee_code_snapshot'  => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'full_name_snapshot'      => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'office_id_snapshot'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'office_name_snapshot'    => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'position_snapshot'       => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'atm_account_no_snapshot' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
        ];
        $toAdd = [];
        foreach ($snapshotFields as $col => $def) {
            if (!$this->db->fieldExists($col, 'payroll_records')) {
                $toAdd[$col] = $def;
            }
        }
        if ($toAdd !== []) {
            $this->forge->addColumn('payroll_records', $toAdd);
        }

        // 4. Deduplicate accidental duplicate (employee_id, payroll_period) rows keeping latest id
        $prTable = $this->db->prefixTable('payroll_records');
        $dups = $this->db->query(
            "SELECT employee_id, payroll_period, MAX(id) AS keep_id
             FROM {$prTable}
             GROUP BY employee_id, payroll_period
             HAVING COUNT(*) > 1"
        )->getResultArray();

        foreach ($dups as $dup) {
            $this->db->table('payroll_records')
                ->where('employee_id', $dup['employee_id'])
                ->where('payroll_period', $dup['payroll_period'])
                ->where('id !=', $dup['keep_id'])
                ->delete();
        }

        // 5. Indexes (safe across MySQLi and SQLite3)
        $this->createIndexSafe("CREATE UNIQUE INDEX ux_payroll_emp_period ON {$prTable} (employee_id, payroll_period)");
        $dedTable = $this->db->prefixTable('deductions');
        $this->createIndexSafe("CREATE INDEX idx_deductions_employee_id ON {$dedTable} (employee_id)");
        $empTable = $this->db->prefixTable('employees');
        $this->createIndexSafe("CREATE INDEX idx_employees_office_id ON {$empTable} (office_id)");
    }

    public function down()
    {
        $this->forge->dropTable('payroll_periods', true);
        $this->forge->dropTable('app_settings', true);

        $cols = [
            'deduction_snapshot', 'salary_rate_snapshot', 'employee_code_snapshot',
            'full_name_snapshot', 'office_id_snapshot', 'office_name_snapshot',
            'position_snapshot', 'atm_account_no_snapshot',
        ];
        foreach ($cols as $col) {
            if ($this->db->fieldExists($col, 'payroll_records')) {
                $this->forge->dropColumn('payroll_records', $col);
            }
        }
    }

    private function createIndexSafe(string $sql): void
    {
        try {
            $this->db->query($sql);
        } catch (\Throwable $e) {
            // Index already exists on re-run; ignore.
        }
    }
}
