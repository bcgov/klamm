<?php

return [
    /*
     * How long to retain uploaded anonymization metadata CSVs after processing completes.
     *
     * Files are retained to support auditing and troubleshooting, then purged by a scheduled command.
     */
    'upload_retention_days' => (int) env('ANONYMIZATION_UPLOAD_RETENTION_DAYS', 30),
];
