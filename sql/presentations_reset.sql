-- Modul přihlašování na prezentace
-- MySQL 8.0+, utf8mb4. Skript je záměrně resetovací.
-- Spusťte jej pouze po exportu stávajících dat.
-- Staré tabulky `subjects` a `presentations` záměrně nemaže; mohou být
-- používány jinou částí webu. Nová aplikace je nečte.

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS presentation_registrations;
DROP TABLE IF EXISTS presentation_event_dates;
DROP TABLE IF EXISTS presentation_subjects;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE presentation_subjects (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(64) NULL,
    name VARCHAR(255) NOT NULL,
    university VARCHAR(255) NOT NULL DEFAULT '',
    slots_per_date SMALLINT UNSIGNED NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_presentation_subjects_code (code),
    CONSTRAINT chk_presentation_subjects_slots CHECK (slots_per_date BETWEEN 1 AND 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE presentation_event_dates (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    subject_id BIGINT UNSIGNED NOT NULL,
    presentation_date DATE NOT NULL,
    note VARCHAR(1000) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_presentation_event_dates_subject_date (subject_id, presentation_date),
    KEY ix_presentation_event_dates_date (presentation_date),
    CONSTRAINT fk_presentation_event_dates_subject FOREIGN KEY (subject_id)
        REFERENCES presentation_subjects (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE presentation_registrations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    date_id BIGINT UNSIGNED NOT NULL,
    slot_number SMALLINT UNSIGNED NOT NULL,
    presenter VARCHAR(500) NOT NULL,
    responsible_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    status ENUM('active','cancelled') NOT NULL DEFAULT 'active',
    active_slot SMALLINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN status='active' THEN slot_number ELSE NULL END) STORED,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_presentation_registrations_token (token_hash),
    UNIQUE KEY uq_presentation_registrations_slot (date_id, active_slot),
    KEY ix_presentation_registrations_date_status (date_id, status),
    CONSTRAINT chk_presentation_registrations_slot CHECK (slot_number BETWEEN 1 AND 100),
    CONSTRAINT fk_presentation_registrations_date FOREIGN KEY (date_id)
        REFERENCES presentation_event_dates (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Příklad seed dat:
-- INSERT INTO presentation_subjects (code,name,university,slots_per_date)
-- VALUES ('SOC101','Název předmětu','Masarykova univerzita',4);
-- INSERT INTO presentation_event_dates (subject_id,presentation_date,note)
-- VALUES (LAST_INSERT_ID(),'2027-01-15','Seminář');
