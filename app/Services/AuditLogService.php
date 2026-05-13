<?php

namespace App\Services;

class AuditLogService
{
    public function describe(): string
    {
        return 'Handles audit log persistence for sensitive actions.';
    }
}
