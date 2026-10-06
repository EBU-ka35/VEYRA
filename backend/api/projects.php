<?php
/**
 * /api/projects.php
 * GET | POST | PUT | DELETE
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$user = $auth->requireAuth();
$userId = (int) $user['id'];
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

$stmt = $pdo->prepare(
    'SELECT w.id FROM workspaces w
     JOIN workspace_members wm ON wm.workspace_id = w.id
     WHERE wm.user_id = ? AND wm.status = "active"
     ORDER BY wm.role = "owner" DESC, w.id ASC LIMIT 1'
);
$stmt->execute([$userId]);
$ws = $stmt->fetch();
if (!$ws) {
    Response::error('No workspace found.', 400);
}
$wsId = (int) $ws['id'];

if ($method === 'GET') {
    $q = trim((string) ($_GET['q'] ?? ''));
    $status = trim((string) ($_GET['status'] ?? 'all'));

    $sql = 'SELECT p.*, u.first_name AS owner_first, u.last_name AS owner_last
            FROM projects p
            LEFT JOIN users u ON u.id = p.owner_id
            WHERE p.workspace_id = ?';
    $params = [$wsId];

    if ($status !== '' && $status !== 'all') {
        $sql .= ' AND p.status = ?';
        $params[] = $status;
    }
    if ($q !== '') {
        $sql .= ' AND (p.name LIKE ? OR p.description LIKE ?)';
        $like = '%' . $q . '%';
        $params[] = $like;
        $params[] = $like;
    }
    $sql .= ' ORDER BY p.updated_at DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS total,
                SUM(status = "active") AS active_count,
                SUM(status = "completed") AS completed_count,
                SUM(status = "on_hold") AS on_hold_count
         FROM projects WHERE workspace_id = ?'
    );
    $stmt->execute([$wsId]);
    $kpis = $stmt->fetch();

    Response::success([
        'projects' => $rows,
        'kpis' => [
            'total'     => (int) ($kpis['total'] ?? 0),
            'active'    => (int) ($kpis['active_count'] ?? 0),
            'completed' => (int) ($kpis['completed_count'] ?? 0),
            'on_hold'   => (int) ($kpis['on_hold_count'] ?? 0),
        ],
    ]);
}

if ($method === 'POST') {
    $data = request_data();
    $name = trim((string) ($data['name'] ?? ''));
    if ($name === '') {
        Response::error('Project name is required.', 422);
    }

    $status = (string) ($data['status'] ?? 'planning');
    $priority = (string) ($data['priority'] ?? 'medium');
    $progress = max(0, min(100, (int) ($data['progress'] ?? 0)));

    $allowedStatus = ['planning', 'active', 'on_hold', 'completed', 'cancelled'];
    $allowedPriority = ['low', 'medium', 'high'];
    if (!in_array($status, $allowedStatus, true)) $status = 'planning';
    if (!in_array($priority, $allowedPriority, true)) $priority = 'medium';

    $stmt = $pdo->prepare(
        'INSERT INTO projects (workspace_id, name, description, status, priority, progress, owner_id, start_date, due_date)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $wsId,
        $name,
        trim((string) ($data['description'] ?? '')) ?: null,
        $status,
        $priority,
        $progress,
        $userId,
        $data['start_date'] ?? null,
        $data['due_date'] ?? null,
    ]);

    $id = (int) $pdo->lastInsertId();
    $stmt = $pdo->prepare('SELECT * FROM projects WHERE id = ?');
    $stmt->execute([$id]);

    Response::success(['project' => $stmt->fetch()], 'Project created.', 201);
}

if ($method === 'PUT' || $method === 'PATCH') {
    $data = request_data();
    $id = (int) ($data['id'] ?? $_GET['id'] ?? 0);
    if ($id < 1) {
        Response::error('Project id is required.', 422);
    }

    $stmt = $pdo->prepare('SELECT id FROM projects WHERE id = ? AND workspace_id = ?');
    $stmt->execute([$id, $wsId]);
    if (!$stmt->fetch()) {
        Response::notFound('Project not found.');
    }

    $map = ['name', 'description', 'status', 'priority', 'progress', 'start_date', 'due_date'];
    $fields = [];
    $params = [];
    foreach ($map as $col) {
        if (array_key_exists($col, $data)) {
            $fields[] = "{$col} = ?";
            $val = $data[$col];
            if ($col === 'progress') $val = max(0, min(100, (int) $val));
            $params[] = $val === '' ? null : $val;
        }
    }
    if (!$fields) {
        Response::error('No fields to update.', 422);
    }

    $params[] = $id;
    $params[] = $wsId;
    $pdo->prepare('UPDATE projects SET ' . implode(', ', $fields) . ' WHERE id = ? AND workspace_id = ?')
        ->execute($params);

    $stmt = $pdo->prepare('SELECT * FROM projects WHERE id = ?');
    $stmt->execute([$id]);
    Response::success(['project' => $stmt->fetch()], 'Project updated.');
}

if ($method === 'DELETE') {
    $id = (int) ($_GET['id'] ?? request_data()['id'] ?? 0);
    if ($id < 1) {
        Response::error('Project id is required.', 422);
    }
    $stmt = $pdo->prepare('DELETE FROM projects WHERE id = ? AND workspace_id = ?');
    $stmt->execute([$id, $wsId]);
    if ($stmt->rowCount() < 1) {
        Response::notFound('Project not found.');
    }
    Response::success(null, 'Project deleted.');
}

Response::methodNotAllowed(['GET', 'POST', 'PUT', 'PATCH', 'DELETE']);
