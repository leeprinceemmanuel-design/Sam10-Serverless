<?php

declare(strict_types=1);

namespace App;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Turns one payslip (from PayrollRepository::payslip) into PDF bytes using Dompdf.
 */
final class PayslipPdf
{
    public static function render(array $p): string
    {
        // Lambda can only write to /tmp, so Dompdf's temp and font cache folders go there
        $tmp = sys_get_temp_dir() . '/dompdf';
        if (!is_dir($tmp)) {
            mkdir($tmp, 0777, true);
        }

        $options = new Options();
        $options->setTempDir($tmp);
        $options->setFontCache($tmp);
        $options->setIsRemoteEnabled(false); // never fetch anything from the internet
        $options->setDefaultFont('Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(self::html($p));
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    private static function html(array $p): string
    {
        $e = fn ($v) => htmlspecialchars((string) ($v ?? 'N/A'), ENT_QUOTES, 'UTF-8');
        $money = fn (float $v) => number_format($v, 2);

        $generated = (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Manila')))
            ->format('F j, Y g:i A');

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: #222; margin: 0; }
    .header { border-bottom: 3px solid #1f4e79; padding-bottom: 10px; margin-bottom: 18px; }
    .company { font-size: 20px; font-weight: bold; color: #1f4e79; }
    .sub { color: #666; font-size: 11px; }
    .title { float: right; text-align: right; }
    .title h1 { margin: 0; font-size: 22px; letter-spacing: 3px; color: #1f4e79; }
    h2 { font-size: 13px; color: #1f4e79; border-bottom: 1px solid #ccc; padding-bottom: 4px; margin: 20px 0 8px; }
    table { width: 100%; border-collapse: collapse; }
    .info td { padding: 5px 4px; vertical-align: top; }
    .info td.label { width: 22%; color: #666; }
    .info td.value { width: 28%; font-weight: bold; }
    .pay th { background: #1f4e79; color: #fff; text-align: left; padding: 7px; }
    .pay td { padding: 7px; border-bottom: 1px solid #e3e3e3; }
    .pay .amt { text-align: right; }
    .net { margin-top: 18px; background: #eaf1f8; border: 1px solid #1f4e79; padding: 12px; }
    .net .label { font-size: 13px; font-weight: bold; color: #1f4e79; }
    .net .value { float: right; font-size: 18px; font-weight: bold; color: #1f4e79; }
    .footer { margin-top: 40px; font-size: 10px; color: #888; text-align: center; border-top: 1px solid #ddd; padding-top: 8px; }
</style>
</head>
<body>
    <div class="header">
        <div class="title">
            <h1>PAYSLIP</h1>
            <div class="sub">Payroll ID: {$e($p['payroll_id'])}</div>
        </div>
        <div class="company">SAM10 Payroll System</div>
        <div class="sub">Serverless Payroll Management API | AWS Lambda</div>
    </div>

    <h2>Employee Details</h2>
    <table class="info">
        <tr>
            <td class="label">Employee Name</td><td class="value">{$e($p['employee_name'])}</td>
            <td class="label">Employee ID</td><td class="value">{$e($p['employee_id'])}</td>
        </tr>
        <tr>
            <td class="label">Department</td><td class="value">{$e($p['department'])}</td>
            <td class="label">Position</td><td class="value">{$e($p['position'])}</td>
        </tr>
        <tr>
            <td class="label">Email</td><td class="value">{$e($p['email'])}</td>
            <td class="label"></td><td class="value"></td>
        </tr>
    </table>

    <h2>Pay Period</h2>
    <table class="info">
        <tr>
            <td class="label">Period Start</td><td class="value">{$e($p['pay_period_start'])}</td>
            <td class="label">Period End</td><td class="value">{$e($p['pay_period_end'])}</td>
        </tr>
    </table>

    <h2>Payroll Summary</h2>
    <table class="pay">
        <tr><th>Description</th><th class="amt">Amount</th></tr>
        <tr><td>Gross Pay</td><td class="amt">{$money($p['gross_pay'])}</td></tr>
        <tr><td>Total Deductions (Gross Pay - Net Pay)</td><td class="amt">{$money($p['deductions'])}</td></tr>
    </table>

    <div class="net">
        <span class="value">{$money($p['net_pay'])}</span>
        <span class="label">NET PAY</span>
    </div>

    <div class="footer">
        Generated on {$generated} (Asia/Manila) | This is a system-generated payslip for a laboratory activity using a synthetic dataset.
    </div>
</body>
</html>
HTML;
    }
}
