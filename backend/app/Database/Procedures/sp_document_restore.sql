CREATE PROCEDURE sp_document_restore(
  IN  p_document_id BIGINT UNSIGNED,
  IN  p_restored_by BIGINT UNSIGNED,
  OUT p_status_code VARCHAR(30),
  OUT p_message VARCHAR(255)
)
sp_document_restore: BEGIN
  DECLARE v_folder_id BIGINT UNSIGNED;
  DECLARE v_exists INT DEFAULT 0;
  DECLARE v_folder_deleted INT DEFAULT 0;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN ROLLBACK; SET p_status_code = 'INTERNAL_ERROR', p_message = 'Unexpected database error while restoring document.'; END;
  START TRANSACTION;

  SELECT COUNT(*), MAX(folder_id) INTO v_exists, v_folder_id
    FROM documents WHERE id = p_document_id AND deleted_at IS NOT NULL FOR UPDATE;
  IF v_exists = 0 THEN
    ROLLBACK; SET p_status_code = 'DOCUMENT_NOT_FOUND', p_message = 'Document does not exist or is not in trash.'; LEAVE sp_document_restore;
  END IF;

  SELECT COUNT(*) INTO v_folder_deleted FROM folders WHERE id = v_folder_id AND deleted_at IS NOT NULL;
  IF v_folder_deleted > 0 THEN
    ROLLBACK; SET p_status_code = 'PARENT_STILL_DELETED', p_message = 'Restore the containing folder first.'; LEAVE sp_document_restore;
  END IF;

  UPDATE documents SET deleted_at = NULL WHERE id = p_document_id;
  INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details)
    VALUES (p_restored_by, 'DOCUMENT_RESTORED', 'DOCUMENT', p_document_id, JSON_OBJECT());
  COMMIT;
  SET p_status_code = 'OK', p_message = 'Document restored.';
END
