-- ============================================================
-- VEYRA Database Schema
-- MySQL 8.0+ / MariaDB 10.5+
-- ============================================================

CREATE DATABASE IF NOT EXISTS veyra
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE veyra;

-- ----------------------------------------------------------
-- Users
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    first_name      VARCHAR(100) NOT NULL,
    last_name       VARCHAR(100) NOT NULL,
    email           VARCHAR(255) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    company         VARCHAR(255) DEFAULT NULL,
    role            VARCHAR(50)  DEFAULT NULL,
    avatar_initials VARCHAR(4)   DEFAULT NULL,
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    email_verified  TINYINT(1)   NOT NULL DEFAULT 0,
    remember_token  VARCHAR(100) DEFAULT NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login_at   DATETIME     DEFAULT NULL,
    INDEX idx_users_email (email),
    INDEX idx_users_active (is_active)
) ENGINE=InnoDB;

-- ----------------------------------------------------------
-- Password reset tokens
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS password_resets (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    token      VARCHAR(64)  NOT NULL UNIQUE,
    expires_at DATETIME     NOT NULL,
    used_at    DATETIME     DEFAULT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_pr_token (token),
    INDEX idx_pr_expires (expires_at)
) ENGINE=InnoDB;

-- ----------------------------------------------------------
-- Workspaces
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS workspaces (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(150) NOT NULL,
    slug       VARCHAR(150) NOT NULL UNIQUE,
    owner_id   INT UNSIGNED NOT NULL,
    plan       ENUM('free','starter','business','enterprise') NOT NULL DEFAULT 'free',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ----------------------------------------------------------
-- Workspace members
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS workspace_members (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id INT UNSIGNED NOT NULL,
    user_id      INT UNSIGNED NOT NULL,
    role         ENUM('owner','admin','member','viewer') NOT NULL DEFAULT 'member',
    department   VARCHAR(100) DEFAULT NULL,
    status       ENUM('active','invited','inactive') NOT NULL DEFAULT 'active',
    joined_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ws_user (workspace_id, user_id),
    FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ----------------------------------------------------------
-- Customers
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS customers (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id INT UNSIGNED NOT NULL,
    name         VARCHAR(200) NOT NULL,
    email        VARCHAR(255) DEFAULT NULL,
    company      VARCHAR(200) DEFAULT NULL,
    phone        VARCHAR(50)  DEFAULT NULL,
    status       ENUM('active','lead','inactive') NOT NULL DEFAULT 'lead',
    notes        TEXT DEFAULT NULL,
    created_by   INT UNSIGNED DEFAULT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_cust_ws (workspace_id),
    INDEX idx_cust_status (status)
) ENGINE=InnoDB;

-- ----------------------------------------------------------
-- Projects
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS projects (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id INT UNSIGNED NOT NULL,
    name         VARCHAR(200) NOT NULL,
    description  TEXT DEFAULT NULL,
    status       ENUM('planning','active','on_hold','completed','cancelled') NOT NULL DEFAULT 'planning',
    priority     ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
    progress     TINYINT UNSIGNED NOT NULL DEFAULT 0,
    owner_id     INT UNSIGNED DEFAULT NULL,
    start_date   DATE DEFAULT NULL,
    due_date     DATE DEFAULT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_proj_ws (workspace_id),
    INDEX idx_proj_status (status)
) ENGINE=InnoDB;

-- ----------------------------------------------------------
-- Tasks
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS tasks (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id INT UNSIGNED NOT NULL,
    project_id   INT UNSIGNED DEFAULT NULL,
    title        VARCHAR(255) NOT NULL,
    notes        TEXT DEFAULT NULL,
    status       ENUM('todo','progress','review','done') NOT NULL DEFAULT 'todo',
    priority     ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
    assignee_id  INT UNSIGNED DEFAULT NULL,
    due_date     DATE DEFAULT NULL,
    created_by   INT UNSIGNED DEFAULT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at DATETIME DEFAULT NULL,
    FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
    FOREIGN KEY (assignee_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_task_ws (workspace_id),
    INDEX idx_task_status (status),
    INDEX idx_task_assignee (assignee_id)
) ENGINE=InnoDB;

-- ----------------------------------------------------------
-- Reports
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS reports (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id INT UNSIGNED NOT NULL,
    name         VARCHAR(200) NOT NULL,
    type         ENUM('revenue','customers','projects','team','custom','operations') NOT NULL DEFAULT 'custom',
    period       VARCHAR(50) DEFAULT 'Last 30 days',
    format       ENUM('CSV','PDF','XLSX') NOT NULL DEFAULT 'CSV',
    status       ENUM('ready','generating','scheduled','failed') NOT NULL DEFAULT 'generating',
    notes        TEXT DEFAULT NULL,
    file_path    VARCHAR(500) DEFAULT NULL,
    created_by   INT UNSIGNED DEFAULT NULL,
    generated_at DATETIME DEFAULT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_rep_ws (workspace_id)
) ENGINE=InnoDB;

-- ----------------------------------------------------------
-- Activity log (optional audit)
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS activity_log (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id INT UNSIGNED DEFAULT NULL,
    user_id      INT UNSIGNED DEFAULT NULL,
    action       VARCHAR(100) NOT NULL,
    entity_type  VARCHAR(50)  DEFAULT NULL,
    entity_id    INT UNSIGNED DEFAULT NULL,
    meta         JSON DEFAULT NULL,
    ip_address   VARCHAR(45)  DEFAULT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_act_ws (workspace_id),
    INDEX idx_act_user (user_id),
    INDEX idx_act_created (created_at)
) ENGINE=InnoDB;

-- ----------------------------------------------------------
-- Seed demo user (password: veyra2026)
-- Hash generated with password_hash('veyra2026', PASSWORD_DEFAULT)
-- ----------------------------------------------------------
INSERT INTO users (first_name, last_name, email, password_hash, company, role, avatar_initials, is_active, email_verified)
VALUES (
    'Demo',
    'User',
    'demo@veyra.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'Veyra Inc',
    'founder',
    'DU',
    1,
    1
)
ON DUPLICATE KEY UPDATE email = email;

-- Note: The hash above is a placeholder. Run seed.php after install
-- to generate a correct hash for "veyra2026".
