<?php
/**
 * GET /api/auth/me.php
 * Returns current authenticated user.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

require_method('GET');

$user = $auth->requireAuth();

Response::success(['user' => $user]);
