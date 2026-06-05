CREATE TABLE IF NOT EXISTS audit_log (
    id              VARCHAR(36)    NOT NULL PRIMARY KEY,
    aggregate_type  VARCHAR(255)   NOT NULL,
    aggregate_id    VARCHAR(255)   NOT NULL,
    action          VARCHAR(10)    NOT NULL,
    old_state       JSON           DEFAULT NULL,
    new_state       JSON           DEFAULT NULL,
    performed_by    VARCHAR(255)   NOT NULL,
    performed_at    DATETIME(6)    NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    metadata        JSON           DEFAULT NULL,

    INDEX idx_audit_aggregate   (aggregate_type, aggregate_id),
    INDEX idx_audit_performed_by (performed_by),
    INDEX idx_audit_performed_at (performed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
