<?php
/**
 * POST /api/auth/reset-password.php
 * Body: { token, password, confirm_password? }
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

require_method('POST');

$data = request_data();
$token = (string) ($data['token'] ?? '');
$password = (string) ($data['password'] ?? '');
$confirm = (string) ($data['confirm_password'] ?? $data['confirmPassword'] ?? $password);

if ($token === '') {
    Response::error('Reset token is required.', 422);
}

if ($password !== $confirm) {
    Response::error('Passwords do not match.', 422);
}

$auth->resetPassword($token, $password);

Response::success(null, 'Password updated. You can now sign in.');
