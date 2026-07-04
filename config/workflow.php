<?php

return [
    'log_archive_days' => env('WORKFLOW_LOG_ARCHIVE_DAYS', 14),
    'execution_retention_days' => env('WORKFLOW_EXECUTION_RETENTION_DAYS', 90),
];
