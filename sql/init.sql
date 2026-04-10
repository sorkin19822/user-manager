CREATE TABLE IF NOT EXISTS users (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    name_first  VARCHAR(100)    NOT NULL,
    name_last   VARCHAR(100)    NOT NULL,
    role        ENUM('admin','user') NOT NULL DEFAULT 'user',
    status      ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO users (name_first, name_last, role, status) VALUES
    ('Alice',   'Johnson',  'admin',  'active'),
    ('Bob',     'Smith',    'user',   'active'),
    ('Carol',   'Williams', 'user',   'inactive'),
    ('David',   'Brown',    'admin',  'active'),
    ('Eva',     'Davis',    'user',   'inactive'),
    ('Frank',   'Miller',   'user',   'active'),
    ('Grace',   'Wilson',   'user',   'active');
