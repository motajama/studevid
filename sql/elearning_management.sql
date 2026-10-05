-- Doplňkové schéma pro správu e-learningových tokenů.
-- Spusťte po existujícím schématu e-learningu.

CREATE TABLE IF NOT EXISTS `e-learning-tokens` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `student_id` int unsigned NOT NULL,
    `token_hash` char(64) NOT NULL,
    `label` varchar(128) NOT NULL DEFAULT '',
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at` datetime DEFAULT NULL,
    `revoked_at` datetime DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_elearning_tokens_hash` (`token_hash`),
    KEY `ix_elearning_tokens_student` (`student_id`),
    CONSTRAINT `fk_elearning_tokens_student`
        FOREIGN KEY (`student_id`) REFERENCES `e-learning-students` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
