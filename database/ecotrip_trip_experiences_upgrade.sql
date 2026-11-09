ALTER TABLE trips
  ADD COLUMN experience_notes TEXT NULL AFTER status,
  ADD COLUMN share_experience TINYINT(1) NOT NULL DEFAULT 0 AFTER experience_notes;
