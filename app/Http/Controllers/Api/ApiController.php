<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class ApiController extends Controller
{
    protected function ok(mixed $data = null, string $message = 'Success', int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data], $status);
    }

    protected function fail(string $message, int $status = 400, array $errors = []): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message, 'errors' => $errors ?: null], $status);
    }

    protected function business(Request $request)
    {
        return $request->attributes->get('current_business');
    }
}
