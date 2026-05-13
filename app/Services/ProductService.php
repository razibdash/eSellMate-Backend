<?php

namespace App\Services;

class ProductService
{
    public function describe(): string
    {
        return 'Handles product CRUD, SKU rules, category filtering, image paths, and low-stock checks.';
    }
}
