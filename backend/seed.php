<?php
/**
 * VEYRA — Database Seed Script
 *
 * Run once after importing schema.sql:
 *   php seed.php
 *
 * Creates demo user: demo@veyra.com / veyra2026
 * Plus sample workspace data.
 */

declare(strict_types=1);

$config = require __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Database.php';

echo "VEYRA Seed\n";
echo str_repeat('-', 40) . "\n";

try {
    $pdo = Database::connect($config['db']);
} catch (Throwable $e) {
    fwrite(STDERR, "DB connection failed: {$e->getMessage()}\n");
    exit(1);
}

$hash = password_hash('veyra2026', PASSWORD_DEFAULT);

// Upsert demo user
$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$stmt->execute(['demo@veyra.com']);
$existing = $stmt->fetch();

if ($existing) {
    $userId = (int) $existing['id'];
    $pdo->prepare('UPDATE users SET password_hash = ?, first_name = ?, last_name = ?, company = ?, role = ?, is_active = 1, email_verified = 1 WHERE id = ?')
        ->execute([$hash, 'Demo', 'User', 'Veyra Inc', 'founder', $userId]);
    echo "Updated demo user (id={$userId})\n";
} else {
    $pdo->prepare(
        'INSERT INTO users (first_name, last_name, email, password_hash, company, role, avatar_initials, is_active, email_verified)
         VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1)'
    )->execute(['Demo', 'User', 'demo@veyra.com', $hash, 'Veyra Inc', 'founder', 'DU']);
    $userId = (int) $pdo->lastInsertId();
    echo "Created demo user (id={$userId})\n";
}

// Workspace
$stmt = $pdo->prepare('SELECT id FROM workspaces WHERE owner_id = ? LIMIT 1');
$stmt->execute([$userId]);
$ws = $stmt->fetch();

if ($ws) {
    $wsId = (int) $ws['id'];
    echo "Using existing workspace (id={$wsId})\n";
} else {
    $pdo->prepare('INSERT INTO workspaces (name, slug, owner_id, plan) VALUES (?, ?, ?, ?)')
        ->execute(['Veyra Workspace', 'veyra-workspace-' . $userId, $userId, 'business']);
    $wsId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO workspace_members (workspace_id, user_id, role, status) VALUES (?, ?, ?, ?)')
        ->execute([$wsId, $userId, 'owner', 'active']);
    echo "Created workspace (id={$wsId})\n";
}

// Sample customers
$count = (int) $pdo->query("SELECT COUNT(*) FROM customers WHERE workspace_id = {$wsId}")->fetchColumn();
if ($count === 0) {
    $customers = [
        ['Olivia Carter', 'olivia@northstar.io', 'Northstar Labs', 'active'],
        ['Daniel Morgan', 'daniel@apex.co', 'Apex Systems', 'active'],
        ['Sophia Williams', 'sophia@brightpath.com', 'BrightPath', 'lead'],
        ['Michael Chen', 'mike@cloudnest.io', 'CloudNest', 'active'],
        ['Emma Park', 'emma@designco.com', 'Design Co', 'inactive'],
        ['Alex Rivera', 'alex@mobilehub.app', 'MobileHub', 'lead'],
    ];
    $stmt = $pdo->prepare(
        'INSERT INTO customers (workspace_id, name, email, company, status, created_by) VALUES (?, ?, ?, ?, ?, ?)'
    );
    foreach ($customers as $c) {
        $stmt->execute([$wsId, $c[0], $c[1], $c[2], $c[3], $userId]);
    }
    echo "Seeded " . count($customers) . " customers\n";
}

// Sample projects
$count = (int) $pdo->query("SELECT COUNT(*) FROM projects WHERE workspace_id = {$wsId}")->fetchColumn();
if ($count === 0) {
    $projects = [
        ['Website Redesign', 'active', 'high', 65],
        ['Mobile Platform', 'active', 'medium', 40],
        ['Analytics System', 'planning', 'high', 10],
        ['Customer Portal', 'active', 'medium', 55],
        ['Security Audit', 'on_hold', 'high', 20],
        ['Q4 Data Pipeline', 'planning', 'low', 5],
    ];
    $stmt = $pdo->prepare(
        'INSERT INTO projects (workspace_id, name, status, priority, progress, owner_id) VALUES (?, ?, ?, ?, ?, ?)'
    );
    foreach ($projects as $p) {
        $stmt->execute([$wsId, $p[0], $p[1], $p[2], $p[3], $userId]);
    }
    echo "Seeded " . count($projects) . " projects\n";
}

// Sample tasks
$count = (int) $pdo->query("SELECT COUNT(*) FROM tasks WHERE workspace_id = {$wsId}")->fetchColumn();
if ($count === 0) {
    $firstProject = (int) $pdo->query("SELECT id FROM projects WHERE workspace_id = {$wsId} ORDER BY id ASC LIMIT 1")->fetchColumn();
    $tasks = [
        ['Finalize homepage wireframes', 'progress', 'high', date('Y-m-d', strtotime('+5 days'))],
        ['Review analytics dashboard specs', 'review', 'high', date('Y-m-d', strtotime('+3 days'))],
        ['Set up CI pipeline', 'todo', 'medium', date('Y-m-d', strtotime('+12 days'))],
        ['Deploy staging environment', 'done', 'medium', date('Y-m-d', strtotime('-5 days'))],
        ['Write launch email sequence', 'progress', 'high', date('Y-m-d', strtotime('+9 days'))],
        ['Security audit remediation', 'todo', 'high', date('Y-m-d', strtotime('-8 days'))],
    ];
    $stmt = $pdo->prepare(
        'INSERT INTO tasks (workspace_id, project_id, title, status, priority, assignee_id, due_date, created_by, completed_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    foreach ($tasks as $t) {
        $completed = $t[1] === 'done' ? date('Y-m-d H:i:s') : null;
        $stmt->execute([$wsId, $firstProject ?: null, $t[0], $t[1], $t[2], $userId, $t[3], $userId, $completed]);
    }
    echo "Seeded " . count($tasks) . " tasks\n";
}

echo str_repeat('-', 40) . "\n";
echo "Done.\n";
echo "Login: demo@veyra.com / veyra2026\n";
