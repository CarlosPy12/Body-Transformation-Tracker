ALTER TABLE glp1_injections
  ADD COLUMN reminder_minutes_before INT UNSIGNED NULL DEFAULT 1440 AFTER notes,
  ADD COLUMN reminder_repeat_minutes INT UNSIGNED NULL DEFAULT 0 AFTER reminder_minutes_before;

ALTER TABLE workout_sessions
  ADD COLUMN reminder_minutes_before INT UNSIGNED NULL DEFAULT 60 AFTER notes,
  ADD COLUMN reminder_repeat_minutes INT UNSIGNED NULL DEFAULT 0 AFTER reminder_minutes_before;
