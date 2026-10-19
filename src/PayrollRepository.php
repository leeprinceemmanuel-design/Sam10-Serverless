<?php

declare(strict_types=1);

namespace App;

/**
 * All the payroll logic: loads the CSV files once and answers the API questions.
 * No database is used. The CSV files in /data are the "database".
 */
final class PayrollRepository
{
    private array $employees = [];   // keyed by employee id
    private array $departments = []; // keyed by department id
    private array $positions = [];   // keyed by position id
    private array $payroll = [];     // keyed by payroll id

    public function __construct(private readonly string $dataDir)
    {
        foreach (CsvReader::read($dataDir . '/employees.csv') as $row) {
            $this->employees[(int) $row['id']] = $row;
        }
        foreach (CsvReader::read($dataDir . '/payroll.csv') as $row) {
            $this->payroll[(int) $row['id']] = $row;
        }

        // departments.csv and positions.csv are optional extras.
        // They only add the department name and job title to payslips.
        if (is_file($dataDir . '/departments.csv')) {
            foreach (CsvReader::read($dataDir . '/departments.csv') as $row) {
                $this->departments[(int) $row['id']] = $row;
            }
        }
        if (is_file($dataDir . '/positions.csv')) {
            foreach (CsvReader::read($dataDir . '/positions.csv') as $row) {
                $this->positions[(int) $row['id']] = $row;
            }
        }
    }

    /** GET /api/employees — the employee records with the real CSV column names. */
    public function employees(): array
    {
        $list = [];
        foreach ($this->employees as $row) {
            $list[] = [
                'id'            => (int) $row['id'],
                'full_name'     => $row['full_name'],
                'email'         => $row['email'],
                'position_id'   => self::intOrNull($row['position_id'] ?? null),
                'department_id' => self::intOrNull($row['department_id'] ?? null),
                'manager_id'    => self::intOrNull($row['manager_id'] ?? null),
            ];
        }
        return $list;
    }

    /** GET /api/reports/payroll — totals for all payroll records. */
    public function payrollReport(): array
    {
        // Add up in centavos (whole numbers) so the totals have no rounding errors
        $grossCents = 0;
        $netCents = 0;
        $negativeDeductions = 0;

        foreach ($this->payroll as $row) {
            $gross = self::toCents($row['gross_pay']);
            $net = self::toCents($row['net_pay']);
            $grossCents += $gross;
            $netCents += $net;
            if ($net > $gross) {
                $negativeDeductions++;
            }
        }

        return [
            'total_payroll_records' => count($this->payroll),
            'total_gross_pay'       => $grossCents / 100,
            'total_deductions'      => ($grossCents - $netCents) / 100, // deductions = gross - net
            'total_net_pay'         => $netCents / 100,
            // The dataset is synthetic: some rows have net pay higher than gross pay.
            'records_with_negative_deductions' => $negativeDeductions,
        ];
    }

    /** GET /api/payslips/{payroll_id} — one payslip, or null if the id does not exist. */
    public function payslip(int $payrollId): ?array
    {
        $row = $this->payroll[$payrollId] ?? null;
        if ($row === null) {
            return null;
        }

        $employeeId = (int) $row['employee_id'];
        $employee = $this->employees[$employeeId] ?? null;
        $department = $employee ? ($this->departments[(int) $employee['department_id']] ?? null) : null;
        $position = $employee ? ($this->positions[(int) $employee['position_id']] ?? null) : null;

        $grossCents = self::toCents($row['gross_pay']);
        $netCents = self::toCents($row['net_pay']);

        return [
            'payroll_id'       => $payrollId,
            'employee_id'      => $employeeId,
            'employee_name'    => $employee['full_name'] ?? 'Unknown employee',
            'email'            => $employee['email'] ?? null,
            'department'       => $department['name'] ?? null,
            'position'         => $position['title'] ?? null,
            'pay_period_start' => $row['pay_period_start'],
            'pay_period_end'   => $row['pay_period_end'],
            'gross_pay'        => $grossCents / 100,
            'deductions'       => ($grossCents - $netCents) / 100,
            'net_pay'          => $netCents / 100,
        ];
    }

    /** "14131.54" -> 1413154 */
    private static function toCents(?string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    private static function intOrNull(?string $value): ?int
    {
        return $value === null ? null : (int) $value;
    }
}
