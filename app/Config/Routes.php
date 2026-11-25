<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// Authentication Routes
$routes->get('/', 'Auth::login');
$routes->get('auth/login', 'Auth::login');
$routes->post('auth/authenticate', 'Auth::authenticate');
$routes->get('auth/logout', 'Auth::logout');

// Dashboard
$routes->get('dashboard', 'Dashboard::index', ['filter' => 'auth']);

// Employee Management
$routes->group('employee', ['filter' => 'auth'], function($routes) {
    $routes->get('/', 'Employee::index');
    $routes->get('create', 'Employee::create');
    $routes->get('create-jo', 'Employee::createJo');
    $routes->post('store', 'Employee::store');
    $routes->get('edit/(:num)', 'Employee::edit/$1');
    $routes->post('update/(:num)', 'Employee::update/$1');
    $routes->post('delete/(:num)', 'Employee::delete/$1');
    $routes->post('reactivate/(:num)', 'Employee::reactivate/$1');
    $routes->post('deleteAll', 'Employee::deleteAll');
    $routes->post('import', 'Employee::import');
});

// Office Management (AJAX from modal)
$routes->group('office', ['filter' => 'auth'], function($routes) {
    $routes->post('store', 'Office::store');
    $routes->post('delete/(:num)', 'Office::delete/$1');
});

// Deduction Management
$routes->group('deduction', ['filter' => 'auth'], function($routes) {
    $routes->get('/', 'Deduction::index');
    $routes->get('manage/(:num)', 'Deduction::manage/$1');
    $routes->post('update', 'Deduction::update');
});

// Payroll Processing
$routes->group('payroll', ['filter' => 'auth'], function($routes) {
    $routes->get('/', 'Payroll::index');
    $routes->get('export', 'Payroll::export');
    $routes->get('print', 'Payroll::printAll');
    $routes->post('process/(:num)', 'Payroll::process/$1');
    $routes->post('process-batch', 'Payroll::processBatch');
    $routes->post('lock', 'Payroll::lock');
    $routes->post('unlock', 'Payroll::unlock');
});

// Payslip Generation
$routes->group('payslip', ['filter' => 'auth'], function($routes) {
    $routes->get('/', 'Payslip::index');
    $routes->get('preview/(:num)', 'Payslip::preview/$1');
    $routes->get('individual/(:num)', 'Payslip::individual/$1');
    $routes->get('batchPrint', 'Payslip::batchPrint');
});

// Reports
$routes->group('report', ['filter' => 'auth'], function($routes) {
    $routes->get('/', 'Report::index');
    $routes->get('generate', 'Report::generate');
});

// LGU Settings (Admin Only)
$routes->group('settings', ['filter' => 'auth'], function($routes) {
    $routes->get('/', 'Settings::index');
    $routes->post('update', 'Settings::update');
});

// Database Backup & Restore (Admin Only)
$routes->group('backup', ['filter' => 'auth'], function($routes) {
    $routes->get('/', 'Backup::index');
    $routes->post('download', 'Backup::download');
    $routes->post('restore', 'Backup::restore');
});

// User Management (Admin Only)
$routes->group('user', ['filter' => 'auth'], function($routes) {
    $routes->get('/', 'User::index');
    $routes->post('register', 'User::register');
    $routes->get('settings', 'User::settings');
    $routes->post('update_password', 'User::updatePassword');
});
