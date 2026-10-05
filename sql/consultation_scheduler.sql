-- Správa termínů konzultací. Spusťte jednou v databázi students.
SET @db := DATABASE();
SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE scheduler ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1 AFTER onlyonline',
  'SELECT 1') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='scheduler' AND COLUMN_NAME='active');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
