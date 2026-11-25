<?php

use App\Controllers\Deduction;
use App\Controllers\Payroll;
use App\Libraries\PayrollCalculator;
use App\Models\DeductionModel;
use App\Models\EmployeeModel;
use App\Models\OfficeModel;
use App\Models\PayrollModel;
use App\Models\PayrollPeriodModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\ControllerTestTrait;
use CodeIgniter\Test\DatabaseTestTrait;

final class PayrollLifecycleTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use ControllerTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';

    private int $officeId;
    private int $rataOfficeId;
    private int $empId;

    protected function setUp(): void
    {
        parent::setUp();

        $officeModel        = new OfficeModel();
        $this->officeId     = (int) $officeModel->insert(['office_name' => "MAYOR'S"]);
        $this->rataOfficeId = (int) $officeModel->insert(['office_name' => 'RATA']);

        $empModel    = new EmployeeModel();
        $this->empId = (int) $empModel->insert([
            'employee_id'       => 'EMP-2026-001',
            'full_name'         => 'Juan Dela Cruz',
            'office_id'         => $this->officeId,
            'position'          => 'Admin Officer',
            'salary_rate'       => 30000.00,
            'employment_status' => 'Regular',
            'is_active'         => 1,
            'atm_account_no'    => '1111-2222-33',
        ]);

        (new DeductionModel())->insert([
            'employee_id'  => $this->empId,
            'gsis_premium' => 2700.00,
            'pagibig_mp2'  => 500.00,
        ]);

        session()->set(['id' => 7, 'username' => 'clerk', 'role' => 'admin', 'isLoggedIn' => true]);
    }

    public function testSnapshotRemainsImmutableAfterLockAndStandingEdit(): void
    {
        $payrollModel = new PayrollModel();
        $periodModel  = new PayrollPeriodModel();

        // 1. Process 2026-10
        $request = service('incomingrequest', null, false);
        $request->setMethod('post')->setGlobal('post', [
            'period'         => '2026-10',
            'first_quincena' => '14000.00',
        ]);
        $this->withRequest($request)->controller(Payroll::class)->execute('process', $this->empId);

        $octRow = $payrollModel->where('employee_id', $this->empId)->where('payroll_period', '2026-10')->first();
        $this->assertNotNull($octRow);
        $this->assertSame(3200.00, (float) $octRow['total_deductions']);
        $this->assertSame(26800.00, (float) $octRow['net_pay']);
        $this->assertSame(14000.00, (float) $octRow['first_quincena']);
        $this->assertSame(7, (int) $octRow['processed_by']);

        // 2. Lock 2026-10
        $periodModel->lock('2026-10', 7, 'Disbursed');
        $this->assertTrue($periodModel->isLocked('2026-10'));

        // 3. Edit standing deductions while viewing locked 2026-10 -> standing changes, locked 2026-10 stays untouched
        $deductReq = service('incomingrequest', null, false);
        $deductReq->setMethod('post')->setGlobal('post', [
            'employee_id'    => $this->empId,
            'period'         => '2026-10',
            'salary_rate'    => '35000.00',
            'atm_account_no' => '9999-8888-77',
            'gsis_premium'   => '3150.00',
            'pagibig_mp2'    => '1000.00',
        ]);
        $this->withRequest($deductReq)->controller(Deduction::class)->execute('update');

        $octAfter = $payrollModel->where('employee_id', $this->empId)->where('payroll_period', '2026-10')->first();
        $octSnap  = PayrollCalculator::extractSnapshot($octAfter);
        $this->assertSame(3200.00, (float) $octAfter['total_deductions']);
        $this->assertSame(2700.00, $octSnap['gsis_premium']);
        $this->assertSame(500.00, $octSnap['pagibig_mp2']);
        $this->assertSame('1111-2222-33', $octAfter['atm_account_no_snapshot']);

        $liveDeduct = (new DeductionModel())->where('employee_id', $this->empId)->first();
        $this->assertSame(3150.00, (float) $liveDeduct['gsis_premium']);
        $this->assertSame(1000.00, (float) $liveDeduct['pagibig_mp2']);
    }

    public function testPastUnlockedEditDoesNotOverwriteStandingRates(): void
    {
        $payrollModel = new PayrollModel();

        // Seed standing at 30000 / 2700, process 2026-09
        $req1 = service('incomingrequest', null, false);
        $req1->setMethod('post')->setGlobal('post', ['period' => '2026-09']);
        $this->withRequest($req1)->controller(Payroll::class)->execute('process', $this->empId);

        // Edit historical 2026-09 (past unlocked month) with different values
        $req2 = service('incomingrequest', null, false);
        $req2->setMethod('post')->setGlobal('post', [
            'employee_id'  => $this->empId,
            'period'       => '2026-09',
            'salary_rate'  => '28000.00',
            'gsis_premium' => '2520.00',
            'pagibig_mp2'  => '0.00',
        ]);
        $this->withRequest($req2)->controller(Deduction::class)->execute('update');

        $sepRow = $payrollModel->where('employee_id', $this->empId)->where('payroll_period', '2026-09')->first();
        $this->assertSame(28000.00, (float) $sepRow['salary_rate_snapshot']);
        $this->assertSame(2520.00, (float) $sepRow['total_deductions']);

        // Standing employee & deductions must remain untouched
        $emp = (new EmployeeModel())->find($this->empId);
        $ded = (new DeductionModel())->where('employee_id', $this->empId)->first();
        $this->assertSame(30000.00, (float) $emp['salary_rate']);
        $this->assertSame(2700.00, (float) $ded['gsis_premium']);
    }

    public function testBatchProcessSkipsInactiveZeroRateAndRataOffices(): void
    {
        $empModel = new EmployeeModel();
        // Inactive employee
        $empModel->insert([
            'employee_id' => 'EMP-2026-002',
            'full_name'   => 'Inactive Person',
            'office_id'   => $this->officeId,
            'position'    => 'Clerk',
            'salary_rate' => 20000.00,
            'is_active'   => 0,
        ]);
        // Zero-rate JO employee
        $empModel->insert([
            'employee_id'       => 'JO-2026-001',
            'full_name'         => 'Zero Rate JO',
            'office_id'         => $this->officeId,
            'position'          => 'Utility',
            'salary_rate'       => 0.00,
            'employment_status' => 'Job Order',
            'is_active'         => 1,
        ]);
        // RATA-only office employee
        $empModel->insert([
            'employee_id' => 'EMP-2026-003',
            'full_name'   => 'RATA Official',
            'office_id'   => $this->rataOfficeId,
            'position'    => 'Dept Head',
            'salary_rate' => 5000.00,
            'is_active'   => 1,
        ]);

        $req = service('incomingrequest', null, false);
        $req->setMethod('post')->setGlobal('post', ['period' => '2026-11', 'office_id' => '']);
        $this->withRequest($req)->controller(Payroll::class)->execute('processBatch');

        $rows = (new PayrollModel())->where('payroll_period', '2026-11')->findAll();
        $this->assertCount(1, $rows);
        $this->assertSame($this->empId, (int) $rows[0]['employee_id']);
        $this->assertSame(7, (int) $rows[0]['processed_by']);
    }
}
