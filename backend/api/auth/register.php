<?php
/**
 * POST /api/auth/register.php
 * Body: { first_name, last_name, email, company, role?, password, confirm_password?, terms }
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

require_method('POST');

$data = request_data();

// Normalize keys from frontend (camelCase or snake_case)
$payload = [
    'first_name'       => $data['first_name'] ?? $data['firstName'] ?? '',
    'last_name'        => $data['last_name'] ?? $data['lastName'] ?? '',
    'email'            => $data['email'] ?? '',
    'company'          => $data['company'] ?? '',
    'role'             => $data['role'] ?? null,
    'password'         => $data['password'] ?? '',
    'confirm_password' => $data['confirm_password'] ?? $data['confirmPassword'] ?? $data['password'] ?? '',
    'terms'            => $data['terms'] ?? false,
];

$user = $auth->register($payload);

Response::success([
    'user' => $user,
], 'Account created successfully.', 201);
