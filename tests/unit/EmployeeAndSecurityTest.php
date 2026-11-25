<?php

use App\Controllers\Employee;
use App\Controllers\User;
use App\Models\EmployeeModel;
use App\Models\OfficeModel;
use App\Models\PayrollModel;
use App\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\ControllerTestTrait;
use CodeIgniter\Test\DatabaseTestTrait;

final class EmployeeAndSecurityTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use ControllerTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';

    private int $officeId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->officeId = (int) (new OfficeModel())->insert(['office_name' => 'ACCTG.']);
    }

    public function testNextCodeDoesNotCollideAfterDeletionAndUpdateValidates(): void
    {
        $model = new EmployeeModel();
        $id1 = (int) $model->insert([
            'employee_id' => 'EMP-2026-001',
            'full_name'   => 'First Employee',
            'office_id'   => $this->officeId,
            'position'    => 'Clerk',
        ]);
        $id2 = (int) $model->insert([
            'employee_id' => 'EMP-2026-002',
            'full_name'   => 'Second Employee',
            'office_id'   => $this->officeId,
            'position'    => 'Clerk',
        ]);

        // Delete first row — nextCode must still produce 003 (not duplicate 002)
        $model->delete($id1);
        $this->assertSame('EMP-2026-003', $model->nextCode('EMP', '2026'));

        // Updating row 2 with its own unchanged employee_id must succeed
        $ok = $model->update($id2, [
            'id'          => $id2,
            'employee_id' => 'EMP-2026-002',
            'full_name'   => 'Second Employee Updated',
            'office_id'   => $this->officeId,
            'position'    => 'Senior Clerk',
        ]);
        $this->assertTrue($ok);
    }

    public function testDeleteSoftDeactivatesWhenPayrollHistoryExists(): void
    {
        session()->set(['id' => 1, 'username' => 'admin', 'role' => 'admin', 'isLoggedIn' => true]);
        $empModel     = new EmployeeModel();
        $payrollModel = new PayrollModel();

        $withHistory = (int) $empModel->insert([
            'employee_id' => 'EMP-2026-010',
            'full_name'   => 'Has History',
            'office_id'   => $this->officeId,
            'position'    => 'Officer',
            'is_active'   => 1,
        ]);
        $payrollModel->insert([
            'employee_id'      => $withHistory,
            'payroll_period'   => '2026-10',
            'gross_pay'        => 20000.00,
            'total_deductions' => 1000.00,
            'net_pay'          => 19000.00,
        ]);

        $noHistory = (int) $empModel->insert([
            'employee_id' => 'EMP-2026-011',
            'full_name'   => 'No History',
            'office_id'   => $this->officeId,
            'position'    => 'Aide',
            'is_active'   => 1,
        ]);

        $this->controller(Employee::class)->execute('delete', $withHistory);
        $this->controller(Employee::class)->execute('delete', $noHistory);

        $row = $empModel->find($withHistory);
        $this->assertNotNull($row);
        $this->assertSame(0, (int) $row['is_active']);
        $this->assertNull($empModel->find($noHistory));
    }

    public function testAdminGuardsOnUserRegisterAndDeleteAll(): void
    {
        $empModel = new EmployeeModel();
        $empModel->insert([
            'employee_id' => 'EMP-2026-020',
            'full_name'   => 'Protected Employee',
            'office_id'   => $this->officeId,
            'position'    => 'Clerk',
        ]);

        // Non-admin cannot register a user
        session()->set(['id' => 2, 'username' => 'staff1', 'role' => 'staff', 'isLoggedIn' => true]);
        $regReq = service('incomingrequest', null, false);
        $regReq->setMethod('post')->setGlobal('post', [
            'username' => 'hacker',
            'password' => 'secret123',
            'role'     => 'admin',
            'name'     => 'Hacker',
        ]);
        $this->withRequest($regReq)->controller(User::class)->execute('register');
        $this->assertNull((new UserModel())->where('username', 'hacker')->first());

        // Non-admin cannot wipe all employees
        $wipeReq = service('incomingrequest', null, false);
        $wipeReq->setMethod('post')->setGlobal('post', ['confirm' => 'DELETE']);
        $this->withRequest($wipeReq)->controller(Employee::class)->execute('deleteAll');
        $this->assertSame(1, $empModel->countAllResults());

        // Admin with wrong confirm string also blocked
        session()->set(['id' => 1, 'username' => 'admin', 'role' => 'admin', 'isLoggedIn' => true]);
        $badConfirmReq = service('incomingrequest', null, false);
        $badConfirmReq->setMethod('post')->setGlobal('post', ['confirm' => 'wrong']);
        $this->withRequest($badConfirmReq)->controller(Employee::class)->execute('deleteAll');
        $this->assertSame(1, $empModel->countAllResults());
    }
}
