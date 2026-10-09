<?php

function ensureSynchroInviteSchema(mysqli $conn): void
{
    static $ready = false;
    if ($ready) {
        return;
    }

    $column = $conn->query("SHOW COLUMNS FROM `synchro_sessions` LIKE 'teacher_user_id'");
    if (!$column) {
        throw new RuntimeException('Unable to inspect Synchro-Quiz session ownership.');
    }
    if ($column->num_rows === 0
        && !$conn->query("ALTER TABLE `synchro_sessions` ADD COLUMN `teacher_user_id` INT NULL")) {
        throw new RuntimeException('Unable to add Synchro-Quiz session ownership.');
    }

    $sql = "CREATE TABLE IF NOT EXISTS `synchro_invitations` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `session_id` INT NOT NULL,
        `student_user_id` INT NOT NULL,
        `status` ENUM('pending', 'accepted', 'declined', 'expired') NOT NULL DEFAULT 'pending',
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `responded_at` DATETIME NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_synchro_session_student` (`session_id`, `student_user_id`),
        KEY `idx_synchro_invite_student_status` (`student_user_id`, `status`),
        KEY `idx_synchro_invite_session_status` (`session_id`, `status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if (!$conn->query($sql)) {
        throw new RuntimeException('Unable to prepare Synchro-Quiz invitations.');
    }

    $ready = true;
}
