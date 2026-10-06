<?php
/**
 * VEYRA — Authentication & Session
 */

declare(strict_types=1);

class Auth
{
    private PDO $db;
    private array $config;

    public function __construct(PDO $db, array $config)
    {
        $this->db = $db;
        $this->config = $config;
    }

    // ----------------------------------------------------------
    // Session
    // ----------------------------------------------------------

    public function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $s = $this->config['session'];
        session_name($s['name']);
        session_set_cookie_params([
            'lifetime' => $s['lifetime'],
            'path'     => '/',
            'secure'   => $s['secure'],
            'httponly' => $s['httponly'],
            'samesite' => $s['samesite'],
        ]);
        session_start();
    }

    public function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public function id(): ?int
    {
        $u = $this->user();
        return $u ? (int) $u['id'] : null;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function requireAuth(): array
    {
        if (!$this->check()) {
            Response::unauthorized('Please sign in to continue.');
        }
        return $this->user();
    }

    public function loginUser(array $user): void
    {
        session_regenerate_id(true);
        unset($user['password_hash']);
        $_SESSION['user'] = $user;
        $_SESSION['logged_in_at'] = time();
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    // ----------------------------------------------------------
    // Register
    // ----------------------------------------------------------

    public function register(array $data): array
    {
        $errors = $this->validateRegister($data);
        if ($errors) {
            Response::error('Validation failed', 422, $errors);
        }

        $email = strtolower(trim($data['email']));

        // Unique email
        $stmt = $this->db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            Response::error('Validation failed', 422, ['email' => 'This email is already registered.']);
        }

        $hash = password_hash($data['password'], PASSWORD_DEFAULT);
        $initials = strtoupper(
            mb_substr(trim($data['first_name']), 0, 1) .
            mb_substr(trim($data['last_name']), 0, 1)
        );

        $stmt = $this->db->prepare(
            'INSERT INTO users (first_name, last_name, email, password_hash, company, role, avatar_initials, email_verified)
             VALUES (?, ?, ?, ?, ?, ?, ?, 0)'
        );
        $stmt->execute([
            trim($data['first_name']),
            trim($data['last_name']),
            $email,
            $hash,
            trim($data['company'] ?? '') ?: null,
            $data['role'] ?? null,
            $initials,
        ]);

        $userId = (int) $this->db->lastInsertId();

        // Create default workspace
        $wsName = trim($data['company'] ?? '') ?: (trim($data['first_name']) . "'s Workspace");
        $slug = $this->slugify($wsName) . '-' . $userId;

        $stmt = $this->db->prepare(
            'INSERT INTO workspaces (name, slug, owner_id, plan) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$wsName, $slug, $userId, 'free']);
        $wsId = (int) $this->db->lastInsertId();

        $stmt = $this->db->prepare(
            'INSERT INTO workspace_members (workspace_id, user_id, role, status) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$wsId, $userId, 'owner', 'active']);

        $user = $this->findById($userId);
        $this->loginUser($user);

        $this->logActivity($wsId, $userId, 'user.registered', 'user', $userId);

        return $user;
    }

    // ----------------------------------------------------------
    // Login
    // ----------------------------------------------------------

    public function login(string $email, string $password, bool $remember = false): array
    {
        $email = strtolower(trim($email));

        if ($email === '' || $password === '') {
            Response::error('Email and password are required.', 422);
        }

        $stmt = $this->db->prepare(
            'SELECT * FROM users WHERE email = ? LIMIT 1'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            Response::error('Invalid email or password.', 401);
        }

        if (!(int) $user['is_active']) {
            Response::error('This account has been deactivated.', 403);
        }

        // Update last login
        $stmt = $this->db->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?');
        $stmt->execute([(int) $user['id']]);

        $this->loginUser($user);

        if ($remember) {
            // Simple remember cookie (email only for prefill — not a secure auth token)
            setcookie('veyra_remember_email', $email, [
                'expires'  => time() + 60 * 60 * 24 * 30,
                'path'     => '/',
                'secure'   => $this->config['session']['secure'],
                'httponly' => false,
                'samesite' => 'Lax',
            ]);
        }

        return $this->user();
    }

    // ----------------------------------------------------------
    // Forgot password
    // ----------------------------------------------------------

    public function requestPasswordReset(string $email): void
    {
        $email = strtolower(trim($email));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('Enter a valid email address.', 422);
        }

        $stmt = $this->db->prepare('SELECT id FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Always respond the same (privacy)
        if (!$user) {
            return;
        }

        $token = bin2hex(random_bytes(32));
        $ttl = (int) ($this->config['auth']['reset_token_ttl'] ?? 3600);
        $expires = date('Y-m-d H:i:s', time() + $ttl);

        // Invalidate old tokens
        $stmt = $this->db->prepare(
            'UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL'
        );
        $stmt->execute([(int) $user['id']]);

        $stmt = $this->db->prepare(
            'INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)'
        );
        $stmt->execute([(int) $user['id'], $token, $expires]);

        // In production: send email with link:
        // {$this->config['app_url']}/reset-password.html?token={$token}
        // For demo we store token so seed/debug can show it.
        if (!empty($this->config['app_debug'])) {
            // Optionally log token path for local testing
            error_log("[VEYRA] Password reset token for {$email}: {$token}");
        }
    }

    public function resetPassword(string $token, string $password): void
    {
        if (strlen($password) < (int) $this->config['auth']['password_min']) {
            Response::error('Password must be at least ' . $this->config['auth']['password_min'] . ' characters.', 422);
        }

        $stmt = $this->db->prepare(
            'SELECT pr.*, u.email FROM password_resets pr
             JOIN users u ON u.id = pr.user_id
             WHERE pr.token = ? AND pr.used_at IS NULL AND pr.expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute([$token]);
        $row = $stmt->fetch();

        if (!$row) {
            Response::error('Invalid or expired reset link.', 400);
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->execute([$hash, (int) $row['user_id']]);

        $stmt = $this->db->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?');
        $stmt->execute([(int) $row['id']]);
    }

    // ----------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, first_name, last_name, email, company, role, avatar_initials,
                    is_active, email_verified, created_at, last_login_at
             FROM users WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    private function validateRegister(array $data): array
    {
        $errors = [];
        $min = (int) $this->config['auth']['password_min'];

        if (empty(trim($data['first_name'] ?? ''))) {
            $errors['first_name'] = 'First name is required.';
        }
        if (empty(trim($data['last_name'] ?? ''))) {
            $errors['last_name'] = 'Last name is required.';
        }
        if (empty(trim($data['email'] ?? ''))) {
            $errors['email'] = 'Email is required.';
        } elseif (!filter_var(trim($data['email']), FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid email address.';
        }
        if (empty(trim($data['company'] ?? ''))) {
            $errors['company'] = 'Company name is required.';
        }
        if (empty($data['password'] ?? '')) {
            $errors['password'] = 'Password is required.';
        } elseif (strlen($data['password']) < $min) {
            $errors['password'] = "Password must be at least {$min} characters.";
        }
        if (($data['password'] ?? '') !== ($data['confirm_password'] ?? $data['password'] ?? '')) {
            if (isset($data['confirm_password'])) {
                $errors['confirm_password'] = 'Passwords do not match.';
            }
        }
        if (empty($data['terms'])) {
            $errors['terms'] = 'You must agree to the Terms and Privacy Policy.';
        }

        return $errors;
    }

    private function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
        return trim($text, '-') ?: 'workspace';
    }

    private function logActivity(?int $wsId, ?int $userId, string $action, ?string $entity = null, ?int $entityId = null): void
    {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO activity_log (workspace_id, user_id, action, entity_type, entity_id, ip_address)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $wsId,
                $userId,
                $action,
                $entity,
                $entityId,
                $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
        } catch (Throwable) {
            // Non-fatal
        }
    }
}
