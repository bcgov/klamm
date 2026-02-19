<?php

return [
    /*
     * How long to retain uploaded anonymization metadata CSVs after processing completes.
     *
     * Files are retained to support auditing and troubleshooting, then purged by a scheduled command.
     */
    'upload_retention_days' => (int) env('ANONYMIZATION_UPLOAD_RETENTION_DAYS', 30),

    /*
     * How long to retain upload staging rows for resume/retry before pruning them.
     *
     * Staging can be significantly larger than upload files, so this is intentionally separate
     * from file retention and defaults to a shorter window.
     */
    'staging_retention_days' => (int) env('ANONYMIZATION_STAGING_RETENTION_DAYS', 7),
];
