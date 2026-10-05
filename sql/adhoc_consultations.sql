-- Ad hoc konzultace mimo běžné studenty.
CREATE TABLE IF NOT EXISTS adhoc_students (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL,
  university VARCHAR(255) NOT NULL DEFAULT '',
  email VARCHAR(255) NOT NULL DEFAULT '',
  uco VARCHAR(64) NOT NULL DEFAULT '',
  status ENUM('unresolved','ongoing','resolved') NOT NULL DEFAULT 'unresolved',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY ix_adhoc_students_name (name), KEY ix_adhoc_students_university (university)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS adhoc_consultations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id BIGINT UNSIGNED NOT NULL,
  consulted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  content TEXT NOT NULL,
  mode ENUM('online','email','in_person') NOT NULL DEFAULT 'in_person',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY ix_adhoc_consultations_date (consulted_at), KEY ix_adhoc_consultations_student (student_id),
  CONSTRAINT fk_adhoc_consultations_student FOREIGN KEY (student_id) REFERENCES adhoc_students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
