<?php
/**
 * POST /api/auth/forgot-password.php
 * Body: { email }
 *
 * Always returns success (privacy — does not reveal if email exists).
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

require_method('POST');

$data = request_data();
$email = (string) ($data['email'] ?? '');

$auth->requestPasswordReset($email);

Response::success(null, 'If an account exists for this email, a reset link has been sent.');
