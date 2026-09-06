CREATE PROCEDURE sp_document_hard_delete(
  IN  p_document_id BIGINT UNSIGNED,
  IN  p_deleted_by BIGINT UNSIGNED,
  OUT p_status_code VARCHAR(30),
  OUT p_message VARCHAR(255)
)
sp_document_hard_delete: BEGIN
  DECLARE v_deleted_at DATETIME;
  DECLARE v_name VARCHAR(200);
  DECLARE v_found INT DEFAULT 0;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN ROLLBACK; SET p_status_code = 'INTERNAL_ERROR', p_message = 'Unexpected database error while purging document.'; END;
  START TRANSACTION;

  SELECT COUNT(*) INTO v_found FROM documents WHERE id = p_document_id FOR UPDATE;
  IF v_found = 0 THEN
    ROLLBACK; SET p_status_code = 'DOCUMENT_NOT_FOUND', p_message = 'Document does not exist.'; LEAVE sp_document_hard_delete;
  END IF;

  SELECT deleted_at, name INTO v_deleted_at, v_name FROM documents WHERE id = p_document_id;
  IF v_deleted_at IS NULL THEN
    ROLLBACK; SET p_status_code = 'NOT_IN_TRASH', p_message = 'Document must be moved to trash before it can be permanently deleted.'; LEAVE sp_document_hard_delete;
  END IF;

  -- Logged before the row disappears — audit_logs.entity_id is a plain
  -- BIGINT (no FK), so the row survives as history after the purge (§19).
  INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details)
    VALUES (p_deleted_by, 'DOCUMENT_PURGED', 'DOCUMENT', p_document_id, JSON_OBJECT('name', v_name));

  -- fk_versions_document is ON DELETE RESTRICT — versions must go first.
  -- The caller is responsible for deleting the S3 objects those versions
  -- pointed at *before* calling this (PHP needs their keys while the rows
  -- still exist).
  DELETE FROM document_versions WHERE document_id = p_document_id;
  DELETE FROM documents WHERE id = p_document_id;

  COMMIT;
  SET p_status_code = 'OK', p_message = 'Document permanently deleted.';
END
