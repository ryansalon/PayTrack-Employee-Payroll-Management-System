<?php

use App\Libraries\DatabaseBackup;
use App\Models\AppSettingModel;
use App\Models\EmployeeModel;
use App\Models\OfficeModel;
use App\Models\PayrollModel;
use App\Models\PayrollPeriodModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

final class DatabaseBackupTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';

    public function testExportAndRestoreRoundTripPreservesJsonSnapshotsAndSettings(): void
    {
        $officeId = (int) (new OfficeModel())->insert(['office_name' => "MAYOR'S OFFICE"]);
        $empId    = (int) (new EmployeeModel())->insert([
            'employee_id'    => 'EMP-2026-001',
            'full_name'      => "O'Connor, Maria",
            'office_id'      => $officeId,
            'position'       => 'Clerk',
            'salary_rate'    => 25000.00,
            'atm_account_no' => '1234-5678',
        ]);
        $snapshot = json_encode(['gsis_premium' => 2250.00, 'pagibig_mp2' => 500.00]);
        (new PayrollModel())->insert([
            'employee_id'        => $empId,
            'payroll_period'     => '2026-11',
            'gross_pay'          => 25000.00,
            'total_deductions'   => 2750.00,
            'net_pay'            => 22250.00,
            'deduction_snapshot' => $snapshot,
            'full_name_snapshot' => "O'Connor, Maria",
        ]);
        (new PayrollPeriodModel())->lock('2026-11', 1, 'Closed & audited');
        (new AppSettingModel())->setMany(['mayor_name' => 'HON. TEST MAYOR']);

        $backup = new DatabaseBackup($this->db);
        $sql    = $backup->exportSql();
        $this->assertStringStartsWith(DatabaseBackup::HEADER_MARKER, $sql);

        // Wipe tables to simulate disaster
        $this->db->table('payroll_records')->emptyTable();
        $this->db->table('employees')->emptyTable();
        $this->db->table('payroll_periods')->emptyTable();
        (new AppSettingModel())->setMany(['mayor_name' => 'OVERWRITTEN']);

        // Restore without writing extra disk safety files during test
        $result = $backup->restoreSql($sql, false);
        $this->assertGreaterThan(0, $result['statements']);

        $restoredEmp = (new EmployeeModel())->find($empId);
        $this->assertSame("O'Connor, Maria", $restoredEmp['full_name']);

        $restoredPr = (new PayrollModel())->where('employee_id', $empId)->where('payroll_period', '2026-11')->first();
        $this->assertSame($snapshot, $restoredPr['deduction_snapshot']);
        $this->assertTrue((new PayrollPeriodModel())->isLocked('2026-11'));
        $this->assertSame('HON. TEST MAYOR', (new AppSettingModel())->getAllSettings()['mayor_name']);
    }

    public function testRestoreRejectsInvalidHeader(): void
    {
        $this->expectException(\RuntimeException::class);
        (new DatabaseBackup($this->db))->restoreSql('DROP TABLE users;', false);
    }
}
