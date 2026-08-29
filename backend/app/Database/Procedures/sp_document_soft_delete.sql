CREATE PROCEDURE sp_document_soft_delete(
  IN  p_document_id BIGINT UNSIGNED,
  IN  p_deleted_by BIGINT UNSIGNED,
  OUT p_status_code VARCHAR(30),
  OUT p_message VARCHAR(255)
)
sp_document_soft_delete: BEGIN
  DECLARE v_exists INT DEFAULT 0;
  DECLARE v_already_deleted INT DEFAULT 0;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN ROLLBACK; SET p_status_code = 'INTERNAL_ERROR', p_message = 'Unexpected database error while deleting document.'; END;
  START TRANSACTION;

  SELECT COUNT(*), SUM(deleted_at IS NOT NULL) INTO v_exists, v_already_deleted
    FROM documents WHERE id = p_document_id FOR UPDATE;
  IF v_exists = 0 THEN
    ROLLBACK; SET p_status_code = 'DOCUMENT_NOT_FOUND', p_message = 'Document does not exist.'; LEAVE sp_document_soft_delete;
  END IF;

  IF v_already_deleted > 0 THEN
    -- idempotent: calling delete twice is not an error (§8.4)
    COMMIT; SET p_status_code = 'OK', p_message = 'Document already in trash.'; LEAVE sp_document_soft_delete;
  END IF;

  UPDATE documents SET deleted_at = NOW() WHERE id = p_document_id;
  INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details)
    VALUES (p_deleted_by, 'DOCUMENT_DELETED', 'DOCUMENT', p_document_id, JSON_OBJECT());
  COMMIT;
  SET p_status_code = 'OK', p_message = 'Document moved to trash.';
END
