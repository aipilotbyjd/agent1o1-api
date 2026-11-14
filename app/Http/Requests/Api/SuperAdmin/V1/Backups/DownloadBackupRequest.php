<?php

namespace App\Http\Requests\Api\SuperAdmin\V1\Backups;

use App\Http\Requests\Api\SuperAdmin\V1\StepUpRequest;

/**
 * A database dump holds every customer's data, so asking for a download link
 * takes a written reason and a fresh authenticator code, like a refund does.
 */
class DownloadBackupRequest extends StepUpRequest {}
