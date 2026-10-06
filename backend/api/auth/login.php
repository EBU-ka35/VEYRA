<?php
/**
 * POST /api/auth/login.php
 * Body: { email, password, remember? }
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

require_method('POST');

$data = request_data();
$email = (string) ($data['email'] ?? '');
$password = (string) ($data['password'] ?? '');
$remember = !empty($data['remember']);

$user = $auth->login($email, $password, $remember);

Response::success([
    'user' => $user,
], 'Signed in successfully.');
