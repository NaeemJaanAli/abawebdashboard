-- Import into a MySQL/MariaDB database named esp32_timer.
CREATE TABLE IF NOT EXISTS device_state (
  id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
  is_running TINYINT(1) NOT NULL DEFAULT 0,
  active_session_id BIGINT UNSIGNED NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO device_state (id, is_running, active_session_id)
VALUES (1, 0, NULL)
ON DUPLICATE KEY UPDATE id = VALUES(id);

CREATE TABLE IF NOT EXISTS timer_sessions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  start_time DATETIME(3) NOT NULL,
  end_time DATETIME(3) NULL,
  duration_ms BIGINT UNSIGNED NULL,
  red_led_on_times TEXT NULL,
  red_press_count INT UNSIGNED NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'running',
  INDEX idx_timer_sessions_start_time (start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE device_state
  ADD CONSTRAINT fk_device_state_active_session
  FOREIGN KEY (active_session_id) REFERENCES timer_sessions(id)
  ON DELETE SET NULL;
