CREATE TABLE IF NOT EXISTS audit_log (
    id              VARCHAR(36)    NOT NULL PRIMARY KEY,
    aggregate_type  VARCHAR(255)   NOT NULL,
    aggregate_id    VARCHAR(255)   NOT NULL,
    action          VARCHAR(10)    NOT NULL,
    old_state       JSONB          DEFAULT NULL,
    new_state       JSONB          DEFAULT NULL,
    performed_by    VARCHAR(255)   NOT NULL,
    performed_at    TIMESTAMP(6)   NOT NULL,
    metadata        JSONB          DEFAULT NULL
);

CREATE INDEX IF NOT EXISTS idx_audit_aggregate ON audit_log (aggregate_type, aggregate_id);
CREATE INDEX IF NOT EXISTS idx_audit_performed_by ON audit_log (performed_by);
CREATE INDEX IF NOT EXISTS idx_audit_performed_at ON audit_log (performed_at);
