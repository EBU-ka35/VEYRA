<?php
/**
 * /api/customers.php
 * GET    — list customers (search, status filter)
 * POST   — create customer
 * PUT    — update customer (?id=)
 * DELETE — delete customer (?id=)
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$user = $auth->requireAuth();
$userId = (int) $user['id'];
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

// Resolve workspace (first owned/joined)
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

// ----------------------------------------------------------
// GET — list
// ----------------------------------------------------------
if ($method === 'GET') {
    $q = trim((string) ($_GET['q'] ?? ''));
    $status = trim((string) ($_GET['status'] ?? 'all'));

    $sql = 'SELECT id, name, email, company, phone, status, notes, created_at, updated_at
            FROM customers WHERE workspace_id = ?';
    $params = [$wsId];

    if ($status !== '' && $status !== 'all') {
        $sql .= ' AND status = ?';
        $params[] = $status;
    }
    if ($q !== '') {
        $sql .= ' AND (name LIKE ? OR email LIKE ? OR company LIKE ?)';
        $like = '%' . $q . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }
    $sql .= ' ORDER BY created_at DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // KPIs
    $stmt = $pdo->prepare(
        'SELECT
            COUNT(*) AS total,
            SUM(status = "active") AS active_count,
            SUM(status = "lead") AS lead_count,
            SUM(status = "inactive") AS inactive_count
         FROM customers WHERE workspace_id = ?'
    );
    $stmt->execute([$wsId]);
    $kpis = $stmt->fetch();

    Response::success([
        'customers' => $rows,
        'kpis' => [
            'total'    => (int) ($kpis['total'] ?? 0),
            'active'   => (int) ($kpis['active_count'] ?? 0),
            'lead'     => (int) ($kpis['lead_count'] ?? 0),
            'inactive' => (int) ($kpis['inactive_count'] ?? 0),
        ],
    ]);
}

// ----------------------------------------------------------
// POST — create
// ----------------------------------------------------------
if ($method === 'POST') {
    $data = request_data();
    $name = trim((string) ($data['name'] ?? ''));
    $email = trim((string) ($data['email'] ?? ''));
    $company = trim((string) ($data['company'] ?? ''));
    $phone = trim((string) ($data['phone'] ?? ''));
    $status = (string) ($data['status'] ?? 'lead');
    $notes = trim((string) ($data['notes'] ?? ''));

    if ($name === '') {
        Response::error('Name is required.', 422, ['name' => 'Name is required.']);
    }
    if (!in_array($status, ['active', 'lead', 'inactive'], true)) {
        $status = 'lead';
    }

    $stmt = $pdo->prepare(
        'INSERT INTO customers (workspace_id, name, email, company, phone, status, notes, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $wsId,
        $name,
        $email ?: null,
        $company ?: null,
        $phone ?: null,
        $status,
        $notes ?: null,
        $userId,
    ]);

    $id = (int) $pdo->lastInsertId();
    $stmt = $pdo->prepare('SELECT * FROM customers WHERE id = ?');
    $stmt->execute([$id]);

    Response::success(['customer' => $stmt->fetch()], 'Customer created.', 201);
}

// ----------------------------------------------------------
// PUT — update
// ----------------------------------------------------------
if ($method === 'PUT' || $method === 'PATCH') {
    $data = request_data();
    $id = (int) ($data['id'] ?? $_GET['id'] ?? 0);
    if ($id < 1) {
        Response::error('Customer id is required.', 422);
    }

    $stmt = $pdo->prepare('SELECT id FROM customers WHERE id = ? AND workspace_id = ?');
    $stmt->execute([$id, $wsId]);
    if (!$stmt->fetch()) {
        Response::notFound('Customer not found.');
    }

    $fields = [];
    $params = [];
    foreach (['name', 'email', 'company', 'phone', 'status', 'notes'] as $col) {
        if (array_key_exists($col, $data)) {
            $fields[] = "{$col} = ?";
            $params[] = $data[$col] === '' ? null : $data[$col];
        }
    }
    if (!$fields) {
        Response::error('No fields to update.', 422);
    }

    $params[] = $id;
    $params[] = $wsId;
    $sql = 'UPDATE customers SET ' . implode(', ', $fields) . ' WHERE id = ? AND workspace_id = ?';
    $pdo->prepare($sql)->execute($params);

    $stmt = $pdo->prepare('SELECT * FROM customers WHERE id = ?');
    $stmt->execute([$id]);

    Response::success(['customer' => $stmt->fetch()], 'Customer updated.');
}

// ----------------------------------------------------------
// DELETE
// ----------------------------------------------------------
if ($method === 'DELETE') {
    $id = (int) ($_GET['id'] ?? request_data()['id'] ?? 0);
    if ($id < 1) {
        Response::error('Customer id is required.', 422);
    }

    $stmt = $pdo->prepare('DELETE FROM customers WHERE id = ? AND workspace_id = ?');
    $stmt->execute([$id, $wsId]);

    if ($stmt->rowCount() < 1) {
        Response::notFound('Customer not found.');
    }

    Response::success(null, 'Customer deleted.');
}

Response::methodNotAllowed(['GET', 'POST', 'PUT', 'PATCH', 'DELETE']);
