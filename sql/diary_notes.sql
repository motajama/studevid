-- Rozšíření PhD deníčků o soukromé týdenní poznámky.
-- Idempotentní: lze spustit opakovaně v databázi aplikace students.
SET @db := DATABASE();
SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE diary_entries ADD COLUMN private_notes TEXT NULL AFTER publications_reflection',
  'SELECT 1') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='diary_entries' AND COLUMN_NAME='private_notes');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE diary_entries ADD COLUMN notes_to_teacher TINYINT(1) NOT NULL DEFAULT 0 AFTER private_notes',
  'SELECT 1') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='diary_entries' AND COLUMN_NAME='notes_to_teacher');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
