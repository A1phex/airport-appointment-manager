-- Dortmund Handling Services GmbH -- internal appointment scheduler
-- Two roles: manager (maintains her available meeting times, approves/rejects
--            appointment requests, manages employee accounts)
--            employee (requests appointments with a topic, edits own profile)

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

CREATE TABLE IF NOT EXISTS manager (
  id            INT PRIMARY KEY AUTO_INCREMENT,
  email         VARCHAR(255) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  name          VARCHAR(255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS employee (
  id              INT PRIMARY KEY AUTO_INCREMENT,
  email           VARCHAR(255) UNIQUE NOT NULL,
  password_hash   VARCHAR(255) NOT NULL,
  name            VARCHAR(255),
  employee_number VARCHAR(30),
  phone           VARCHAR(30)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- A slot is a meeting time the manager makes available.
-- Lifecycle: open -> pending (employee requests with a topic)
--                 -> approved (manager approves) or back to open (reject/withdraw)
CREATE TABLE IF NOT EXISTS slot (
  id               INT PRIMARY KEY AUTO_INCREMENT,
  title            VARCHAR(255) NOT NULL,
  slot_date        DATE NOT NULL,
  slot_time        TIME NOT NULL,
  duration_minutes INT NOT NULL DEFAULT 30,
  created_by       INT NOT NULL,
  status           ENUM('open','pending','approved') NOT NULL DEFAULT 'open',
  requested_by     INT DEFAULT NULL,
  topic            VARCHAR(500) DEFAULT NULL,
  requested_at     DATETIME DEFAULT NULL,
  approved_at      DATETIME DEFAULT NULL,
  FOREIGN KEY (created_by)   REFERENCES manager(id),
  FOREIGN KEY (requested_by) REFERENCES employee(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Snapshot of a request the manager rejected or cancelled, so the employee
-- still sees what happened after the slot itself changed or disappeared.
CREATE TABLE IF NOT EXISTS request_notice (
  id          INT PRIMARY KEY AUTO_INCREMENT,
  employee_id INT NOT NULL,
  kind        ENUM('rejected','cancelled') NOT NULL,
  title       VARCHAR(255) NOT NULL,
  slot_date   DATE NOT NULL,
  slot_time   TIME NOT NULL,
  topic       VARCHAR(500) DEFAULT NULL,
  created_at  DATETIME NOT NULL,
  FOREIGN KEY (employee_id) REFERENCES employee(id) ON DELETE CASCADE,
  INDEX idx_employee_created (employee_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS login_directory (
  email VARCHAR(255) PRIMARY KEY,
  role  ENUM('manager','employee') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed data. All seeded accounts use the password: password123

INSERT INTO manager (email, password_hash, name) VALUES
('manager@dortmund-handling.de', '$2y$10$PeOOsRiKhyzampmVfpNs/eRd4v3ZqMf3t.ozfYr1bVh1xGNN/v/36', 'Ops Manager');

INSERT INTO employee (email, password_hash, name, employee_number, phone) VALUES
('anna.schmidt@dortmund-handling.de', '$2y$10$PeOOsRiKhyzampmVfpNs/eRd4v3ZqMf3t.ozfYr1bVh1xGNN/v/36', 'Anna Schmidt', 'EMP-1001', '+49 231 555 0101'),
('lukas.becker@dortmund-handling.de', '$2y$10$PeOOsRiKhyzampmVfpNs/eRd4v3ZqMf3t.ozfYr1bVh1xGNN/v/36', 'Lukas Becker', 'EMP-1002', '+49 231 555 0102'),
('mia.hoffmann@dortmund-handling.de', '$2y$10$PeOOsRiKhyzampmVfpNs/eRd4v3ZqMf3t.ozfYr1bVh1xGNN/v/36', 'Mia Hoffmann', 'EMP-1003', '+49 231 555 0103');

INSERT INTO login_directory (email, role) VALUES
('manager@dortmund-handling.de', 'manager'),
('anna.schmidt@dortmund-handling.de', 'employee'),
('lukas.becker@dortmund-handling.de', 'employee'),
('mia.hoffmann@dortmund-handling.de', 'employee');

INSERT INTO slot (title, slot_date, slot_time, duration_minutes, created_by, status, requested_by, topic, requested_at, approved_at) VALUES
('1:1 Meeting Window',      '2026-07-22', '09:00:00', 30, 1, 'open',     NULL, NULL, NULL, NULL),
('1:1 Meeting Window',      '2026-07-22', '14:00:00', 30, 1, 'pending',  1,    'Shift swap for August vacation', '2026-07-20 09:15:00', NULL),
('Team Topics / Feedback',  '2026-07-23', '10:30:00', 45, 1, 'approved', 2,    'Pushback tug 4 recurring hydraulic issue', '2026-07-19 14:00:00', '2026-07-20 08:30:00'),
('1:1 Meeting Window',      '2026-07-24', '11:00:00', 30, 1, 'open',     NULL, NULL, NULL, NULL),
('1:1 Meeting Window',      '2026-07-28', '09:00:00', 60, 1, 'open',     NULL, NULL, NULL, NULL);

INSERT INTO request_notice (employee_id, kind, title, slot_date, slot_time, topic, created_at) VALUES
(1, 'rejected',  '1:1 Meeting Window', '2026-07-21', '15:00:00', 'Uniform reimbursement question', '2026-07-18 10:00:00'),
(2, 'cancelled', '1:1 Meeting Window', '2026-07-20', '13:00:00', 'De-icing training slot', '2026-07-17 16:30:00');
