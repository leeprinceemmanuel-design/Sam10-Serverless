<?php

declare(strict_types=1);

namespace App;

use Aws\S3\S3Client;
use RuntimeException;

/**
 * Uploads payslip PDFs to S3 with the AWS SDK for PHP.
 *
 * On Lambda, the SDK picks up the execution role's credentials automatically
 * (from environment variables AWS Lambda sets), so no access keys are in the code.
 */
final class PayslipStorage
{
    /** @return array{bucket: string, key: string, url: string} */
    public static function upload(int $payrollId, string $pdfBytes): array
    {
        $key = "payslips/{$payrollId}.pdf";

        // Local testing only: save to a folder instead of S3 (never set this on Lambda)
        $localDir = getenv('PAYSLIP_LOCAL_DIR');
        if ($localDir) {
            if (!is_dir($localDir . '/payslips')) {
                mkdir($localDir . '/payslips', 0777, true);
            }
            file_put_contents($localDir . '/' . $key, $pdfBytes);
            return ['bucket' => 'local', 'key' => $key, 'url' => 'file://' . realpath($localDir . '/' . $key)];
        }

        $bucket = getenv('PAYSLIP_BUCKET');
        if (!$bucket) {
            throw new RuntimeException('PAYSLIP_BUCKET environment variable is not set.');
        }
        $region = getenv('AWS_REGION') ?: 'ap-southeast-1'; // Lambda sets AWS_REGION for us

        $s3 = new S3Client([
            'region'  => $region,
            'version' => 'latest',
        ]);

        $s3->putObject([
            'Bucket'      => $bucket,
            'Key'         => $key,
            'Body'        => $pdfBytes,
            'ContentType' => 'application/pdf',
        ]);

        return [
            'bucket' => $bucket,
            'key'    => $key,
            'url'    => "https://{$bucket}.s3.{$region}.amazonaws.com/{$key}",
        ];
    }
}
