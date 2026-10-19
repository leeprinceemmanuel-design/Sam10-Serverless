<?php

/**
 * SAM10 - Serverless Payroll Management API
 * Entry point for every request. Bref runs this file on AWS Lambda (php-84-fpm runtime),
 * and API Gateway sends ALL paths here, so we do our own simple routing below.
 */

declare(strict_types=1);

use App\PayrollRepository;
use App\PayslipPdf;
use App\PayslipStorage;

require __DIR__ . '/../vendor/autoload.php';

/** Send a JSON response and stop. */
function respond(int $status, array $body): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rtrim($path, '/') ?: '/'; // treat "/api/employees/" the same as "/api/employees"

try {
    $repo = new PayrollRepository(__DIR__ . '/../data');

    // GET /  -> small index so the base URL shows something useful
    if ($path === '/' && $method === 'GET') {
        respond(200, [
            'success' => true,
            'message' => 'SAM10 Serverless Payroll Management API',
            'endpoints' => [
                'GET /api/employees',
                'GET /api/reports/payroll',
                'GET /api/payslips/{payroll_id}',
                'POST /api/payslips/{payroll_id}/generate',
            ],
        ]);
    }

    // GET /api/employees
    if ($path === '/api/employees') {
        if ($method !== 'GET') {
            respond(405, ['success' => false, 'message' => 'Use GET for this endpoint.']);
        }
        $employees = $repo->employees();
        respond(200, ['success' => true, 'count' => count($employees), 'data' => $employees]);
    }

    // GET /api/reports/payroll
    if ($path === '/api/reports/payroll') {
        if ($method !== 'GET') {
            respond(405, ['success' => false, 'message' => 'Use GET for this endpoint.']);
        }
        respond(200, ['success' => true, 'data' => $repo->payrollReport()]);
    }

    // POST /api/payslips/{payroll_id}/generate  (checked before the GET route below)
    if (preg_match('#^/api/payslips/(\d+)/generate$#', $path, $m)) {
        if ($method !== 'POST') {
            respond(405, ['success' => false, 'message' => 'Use POST for this endpoint.']);
        }
        $payslip = $repo->payslip((int) $m[1]);
        if ($payslip === null) {
            respond(404, ['success' => false, 'message' => "Payroll record {$m[1]} not found."]);
        }

        $pdf = PayslipPdf::render($payslip);
        $file = PayslipStorage::upload($payslip['payroll_id'], $pdf);

        respond(201, [
            'success' => true,
            'message' => 'Payslip generated and uploaded.',
            'data' => [
                'payroll_id'   => $payslip['payroll_id'],
                's3_bucket'    => $file['bucket'],
                's3_key'       => $file['key'],
                'payslip_file' => $file['url'],
            ],
        ]);
    }

    // GET /api/payslips/{payroll_id}
    if (preg_match('#^/api/payslips/([^/]+)$#', $path, $m)) {
        if ($method !== 'GET') {
            respond(405, ['success' => false, 'message' => 'Use GET for this endpoint.']);
        }
        if (!ctype_digit($m[1])) {
            respond(400, ['success' => false, 'message' => 'payroll_id must be a whole number.']);
        }
        $payslip = $repo->payslip((int) $m[1]);
        if ($payslip === null) {
            respond(404, ['success' => false, 'message' => "Payroll record {$m[1]} not found."]);
        }
        respond(200, ['success' => true, 'data' => $payslip]);
    }

    respond(404, ['success' => false, 'message' => "Route not found: {$method} {$path}"]);
} catch (Throwable $e) {
    // The full error goes to CloudWatch Logs; the client gets a short message
    error_log((string) $e);
    respond(500, ['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
