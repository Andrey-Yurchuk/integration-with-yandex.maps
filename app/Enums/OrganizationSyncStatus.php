<?php

namespace App\Enums;

enum OrganizationSyncStatus: string
{
    case Awaiting = 'awaiting';
    case Queued = 'queued';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
