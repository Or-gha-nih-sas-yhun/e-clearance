-- Run on the deployment database before publishing a library evaluation.
-- New tables use InnoDB so publishing/editing and response writes can lock rows.
CREATE TABLE IF NOT EXISTS library_evaluations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    questions JSON NOT NULL,
    created_by VARCHAR(50) NOT NULL,
    published_at TIMESTAMP(6) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX library_evaluations_published_at_index (published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS library_evaluation_responses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    evaluation_id BIGINT UNSIGNED NOT NULL,
    student_id VARCHAR(50) NOT NULL,
    ratings JSON NOT NULL,
    completed_at TIMESTAMP NOT NULL,
    UNIQUE KEY library_evaluation_student_unique (evaluation_id, student_id),
    INDEX library_evaluation_responses_student_id_index (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
