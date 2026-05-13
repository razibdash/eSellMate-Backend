<?php

namespace App\Services;

class InvoiceService
{
    public function describe(): string
    {
        return 'Handles invoice numbering, invoice snapshots, and invoice file generation.';
    }
}
