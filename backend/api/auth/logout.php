<?php
/**
 * POST /api/auth/logout.php
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

require_method('POST', 'GET');

$auth->logout();

Response::success(null, 'Signed out successfully.');
