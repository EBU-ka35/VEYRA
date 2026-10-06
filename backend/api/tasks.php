<?php
/**
 * /api/tasks.php
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
    $priority = trim((string) ($_GET['priority'] ?? 'all'));

    $sql = 'SELECT t.*,
                   p.name AS project_name,
                   u.first_name AS assignee_first, u.last_name AS assignee_last
            FROM tasks t
            LEFT JOIN projects p ON p.id = t.project_id
            LEFT JOIN users u ON u.id = t.assignee_id
            WHERE t.workspace_id = ?';
    $params = [$wsId];

    if ($status !== '' && $status !== 'all') {
        $sql .= ' AND t.status = ?';
        $params[] = $status;
    }
    if ($priority !== '' && $priority !== 'all') {
        $sql .= ' AND t.priority = ?';
        $params[] = $priority;
    }
    if ($q !== '') {
        $sql .= ' AND (t.title LIKE ? OR t.notes LIKE ? OR p.name LIKE ?)';
        $like = '%' . $q . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }
    $sql .= ' ORDER BY
                CASE t.priority WHEN "high" THEN 0 WHEN "medium" THEN 1 ELSE 2 END,
                t.due_date IS NULL, t.due_date ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS total,
                SUM(status IN ("progress","review")) AS in_progress,
                SUM(status = "done") AS done_count,
                SUM(status != "done" AND due_date < CURDATE()) AS overdue
         FROM tasks WHERE workspace_id = ?'
    );
    $stmt->execute([$wsId]);
    $kpis = $stmt->fetch();

    Response::success([
        'tasks' => $rows,
        'kpis' => [
            'total'       => (int) ($kpis['total'] ?? 0),
            'in_progress' => (int) ($kpis['in_progress'] ?? 0),
            'done'        => (int) ($kpis['done_count'] ?? 0),
            'overdue'     => (int) ($kpis['overdue'] ?? 0),
        ],
    ]);
}

if ($method === 'POST') {
    $data = request_data();
    $title = trim((string) ($data['title'] ?? ''));
    if ($title === '') {
        Response::error('Task title is required.', 422);
    }

    $status = (string) ($data['status'] ?? 'todo');
    $priority = (string) ($data['priority'] ?? 'medium');
    if (!in_array($status, ['todo', 'progress', 'review', 'done'], true)) $status = 'todo';
    if (!in_array($priority, ['low', 'medium', 'high'], true)) $priority = 'medium';

    $completedAt = $status === 'done' ? date('Y-m-d H:i:s') : null;

    $stmt = $pdo->prepare(
        'INSERT INTO tasks (workspace_id, project_id, title, notes, status, priority, assignee_id, due_date, created_by, completed_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $wsId,
        !empty($data['project_id']) ? (int) $data['project_id'] : null,
        $title,
        trim((string) ($data['notes'] ?? '')) ?: null,
        $status,
        $priority,
        !empty($data['assignee_id']) ? (int) $data['assignee_id'] : $userId,
        $data['due_date'] ?? null,
        $userId,
        $completedAt,
    ]);

    $id = (int) $pdo->lastInsertId();
    $stmt = $pdo->prepare('SELECT * FROM tasks WHERE id = ?');
    $stmt->execute([$id]);
    Response::success(['task' => $stmt->fetch()], 'Task created.', 201);
}

if ($method === 'PUT' || $method === 'PATCH') {
    $data = request_data();
    $id = (int) ($data['id'] ?? $_GET['id'] ?? 0);
    if ($id < 1) {
        Response::error('Task id is required.', 422);
    }

    $stmt = $pdo->prepare('SELECT * FROM tasks WHERE id = ? AND workspace_id = ?');
    $stmt->execute([$id, $wsId]);
    $existing = $stmt->fetch();
    if (!$existing) {
        Response::notFound('Task not found.');
    }

    $map = ['title', 'notes', 'status', 'priority', 'project_id', 'assignee_id', 'due_date'];
    $fields = [];
    $params = [];
    foreach ($map as $col) {
        if (array_key_exists($col, $data)) {
            $fields[] = "{$col} = ?";
            $params[] = $data[$col] === '' ? null : $data[$col];
        }
    }

    // Auto completed_at
    if (isset($data['status'])) {
        if ($data['status'] === 'done' && $existing['status'] !== 'done') {
            $fields[] = 'completed_at = ?';
            $params[] = date('Y-m-d H:i:s');
        } elseif ($data['status'] !== 'done') {
            $fields[] = 'completed_at = ?';
            $params[] = null;
        }
    }

    if (!$fields) {
        Response::error('No fields to update.', 422);
    }

    $params[] = $id;
    $params[] = $wsId;
    $pdo->prepare('UPDATE tasks SET ' . implode(', ', $fields) . ' WHERE id = ? AND workspace_id = ?')
        ->execute($params);

    $stmt = $pdo->prepare('SELECT * FROM tasks WHERE id = ?');
    $stmt->execute([$id]);
    Response::success(['task' => $stmt->fetch()], 'Task updated.');
}

if ($method === 'DELETE') {
    $id = (int) ($_GET['id'] ?? request_data()['id'] ?? 0);
    if ($id < 1) {
        Response::error('Task id is required.', 422);
    }
    $stmt = $pdo->prepare('DELETE FROM tasks WHERE id = ? AND workspace_id = ?');
    $stmt->execute([$id, $wsId]);
    if ($stmt->rowCount() < 1) {
        Response::notFound('Task not found.');
    }
    Response::success(null, 'Task deleted.');
}

Response::methodNotAllowed(['GET', 'POST', 'PUT', 'PATCH', 'DELETE']);
