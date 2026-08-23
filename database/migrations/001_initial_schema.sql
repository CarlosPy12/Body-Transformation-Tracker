SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  name VARCHAR(120) NOT NULL,
  role ENUM('super_admin','user') NOT NULL DEFAULT 'user',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  two_factor_secret VARCHAR(255) NULL,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY users_email_unique (email),
  KEY users_role_idx (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_settings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  timezone VARCHAR(80) NOT NULL DEFAULT 'Europe/Rome',
  daily_steps_target INT UNSIGNED NOT NULL DEFAULT 10000,
  session_timeout_minutes INT UNSIGNED NULL,
  anomaly_relative_threshold DECIMAL(5,4) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY user_settings_user_unique (user_id),
  CONSTRAINT fk_user_settings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  ip_address VARCHAR(64) NOT NULL,
  attempted_at DATETIME NOT NULL,
  KEY login_attempts_email_time_idx (email, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE measurement_imports (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  file_name VARCHAR(255) NOT NULL,
  file_hash CHAR(64) NOT NULL,
  rows_found INT UNSIGNED NOT NULL DEFAULT 0,
  rows_imported INT UNSIGNED NOT NULL DEFAULT 0,
  rows_duplicates INT UNSIGNED NOT NULL DEFAULT 0,
  rows_flagged INT UNSIGNED NOT NULL DEFAULT 0,
  rows_rejected INT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('analysed','completed','failed') NOT NULL DEFAULT 'analysed',
  error_details JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  KEY measurement_imports_user_created_idx (user_id, created_at),
  CONSTRAINT fk_measurement_imports_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE body_measurements (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  measured_at DATETIME NOT NULL,
  weight_kg DECIMAL(6,2) NULL,
  bmi DECIMAL(5,2) NULL,
  body_fat DECIMAL(6,2) NULL,
  body_water DECIMAL(6,2) NULL,
  muscle DECIMAL(6,2) NULL,
  bone DECIMAL(6,2) NULL,
  left_arm_body_fat DECIMAL(6,2) NULL,
  left_arm_muscle DECIMAL(6,2) NULL,
  right_arm_body_fat DECIMAL(6,2) NULL,
  right_arm_muscle DECIMAL(6,2) NULL,
  left_leg_body_fat DECIMAL(6,2) NULL,
  left_leg_muscle DECIMAL(6,2) NULL,
  right_leg_body_fat DECIMAL(6,2) NULL,
  right_leg_muscle DECIMAL(6,2) NULL,
  trunk_body_fat DECIMAL(6,2) NULL,
  trunk_muscle DECIMAL(6,2) NULL,
  metabolic_age DECIMAL(5,1) NULL,
  heart_rate_bpm DECIMAL(5,1) NULL,
  visceral_fat DECIMAL(5,1) NULL,
  source VARCHAR(80) NOT NULL DEFAULT 'manual',
  measurement_hash CHAR(64) NOT NULL,
  import_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY body_measurements_user_hash_unique (user_id, measurement_hash),
  KEY body_measurements_user_measured_idx (user_id, measured_at),
  KEY body_measurements_import_idx (import_id),
  CONSTRAINT fk_body_measurements_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_body_measurements_import FOREIGN KEY (import_id) REFERENCES measurement_imports(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE goals (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  metric_key VARCHAR(80) NOT NULL,
  target_value DECIMAL(10,2) NOT NULL,
  target_date DATE NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY goals_user_metric_idx (user_id, metric_key, is_active),
  CONSTRAINT fk_goals_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE glp1_medications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  active_ingredient VARCHAR(120) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY glp1_medications_user_idx (user_id),
  CONSTRAINT fk_glp1_medications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE glp1_injections (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  medication_id BIGINT UNSIGNED NOT NULL,
  scheduled_at DATETIME NOT NULL,
  administered_at DATETIME NULL,
  planned_dose_mg DECIMAL(5,2) NOT NULL,
  administered_dose_mg DECIMAL(5,2) NULL,
  status ENUM('scheduled','completed','missed','skipped','cancelled') NOT NULL DEFAULT 'scheduled',
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY glp1_injections_user_scheduled_idx (user_id, scheduled_at),
  KEY glp1_injections_user_administered_idx (user_id, administered_at),
  KEY glp1_injections_user_status_idx (user_id, status),
  CONSTRAINT fk_glp1_injections_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_glp1_injections_medication FOREIGN KEY (medication_id) REFERENCES glp1_medications(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE workout_sessions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  scheduled_at DATETIME NOT NULL,
  completed_at DATETIME NULL,
  workout_type VARCHAR(80) NOT NULL,
  duration_minutes INT UNSIGNED NULL,
  calories_burned INT UNSIGNED NULL,
  status ENUM('scheduled','completed','missed','cancelled') NOT NULL DEFAULT 'scheduled',
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY workout_sessions_user_scheduled_idx (user_id, scheduled_at),
  KEY workout_sessions_user_status_idx (user_id, status),
  CONSTRAINT fk_workout_sessions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE daily_steps (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  step_date DATE NOT NULL,
  steps INT UNSIGNED NOT NULL DEFAULT 0,
  source VARCHAR(80) NOT NULL DEFAULT 'health_sync_drive',
  source_file_id VARCHAR(190) NULL,
  source_file_name VARCHAR(255) NULL,
  source_modified_at DATETIME NULL,
  synced_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY daily_steps_user_date_unique (user_id, step_date),
  KEY daily_steps_user_date_idx (user_id, step_date),
  CONSTRAINT fk_daily_steps_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE push_subscriptions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  endpoint TEXT NOT NULL,
  public_key VARCHAR(255) NOT NULL,
  auth_token VARCHAR(255) NOT NULL,
  user_agent VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY push_subscriptions_user_idx (user_id),
  CONSTRAINT fk_push_subscriptions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notification_preferences (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  injection_reminders TINYINT(1) NOT NULL DEFAULT 1,
  workout_reminders TINYINT(1) NOT NULL DEFAULT 1,
  reminder_hour TINYINT UNSIGNED NOT NULL DEFAULT 9,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY notification_preferences_user_unique (user_id),
  CONSTRAINT fk_notification_preferences_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notification_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  notification_type VARCHAR(80) NOT NULL,
  related_table VARCHAR(80) NULL,
  related_id BIGINT UNSIGNED NULL,
  scheduled_for DATE NOT NULL,
  sent_at DATETIME NOT NULL,
  status ENUM('sent','failed') NOT NULL,
  error_message TEXT NULL,
  UNIQUE KEY notification_log_dedupe (user_id, notification_type, related_table, related_id, scheduled_for),
  KEY notification_log_user_sent_idx (user_id, sent_at),
  CONSTRAINT fk_notification_log_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sync_files (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  provider VARCHAR(80) NOT NULL,
  data_type VARCHAR(80) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  external_file_id VARCHAR(190) NOT NULL,
  file_name VARCHAR(255) NOT NULL,
  external_modified_at DATETIME NULL,
  checksum CHAR(64) NULL,
  status ENUM('processed','failed','skipped') NOT NULL DEFAULT 'processed',
  processed_at DATETIME NULL,
  error_message TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY sync_files_unique (provider, data_type, user_id, external_file_id),
  KEY sync_files_user_type_idx (user_id, provider, data_type),
  CONSTRAINT fk_sync_files_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE body_circumferences (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  measured_at DATETIME NOT NULL,
  circumference_key ENUM('vita','fianchi','torace','braccio_sx','braccio_dx','coscia_sx','coscia_dx','collo','altro') NOT NULL,
  value_cm DECIMAL(6,2) NOT NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY body_circumferences_user_time_idx (user_id, measured_at),
  CONSTRAINT fk_body_circumferences_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE progress_photos (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  taken_at DATETIME NOT NULL,
  storage_path VARCHAR(255) NOT NULL,
  angle VARCHAR(80) NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY progress_photos_user_taken_idx (user_id, taken_at),
  CONSTRAINT fk_progress_photos_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
