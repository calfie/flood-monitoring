<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

$routes->get('/', 'Dashboard::index');

$routes->get('/admin/login', 'Admin::login');
$routes->post('/admin/login', 'Admin::doLogin');
$routes->get('/admin', 'Admin::index');
$routes->get('admin/logout', 'Admin::logout');

// API
$routes->get('/api/devices', 'Api::devices');
$routes->get('/api/history/(:segment)', 'Api::history/$1');
$routes->get('/api/qos/(:segment)', 'Api::qos/$1');

// FIX Buzzer route
$routes->post('/api/buzzer', 'Api::setBuzzer');
$routes->get('admin/log-sensor', 'Admin::logSensor');

// app/Config/Routes.php
$routes->get('admin/load-sensor', 'Admin::loadSensor');

// Log Sensor
$routes->get('admin/log-sensor', 'Admin::logSensor');

// Log QoS (BARU)
$routes->get('admin/log-qos', 'Admin::logQos');
$routes->get('admin/load-qos', 'Admin::loadQos');

// ================== KELOLA ADMIN ==================
$routes->get('admin/users', 'Admin::users');                        // halaman daftar admin
$routes->post('admin/users/create', 'Admin::createUser');           // tambah admin
$routes->post('admin/users/delete/(:segment)', 'Admin::deleteUser/$1'); // hapus admin


$routes->post('admin/delete-log', 'Admin::deleteLog'); // <-- penting
$routes->post('admin/delete-logs', 'Admin::deleteLogs');

// app/Config/Routes.php
