CREATE PROCEDURE sp_folder_restore(
  IN  p_folder_id BIGINT UNSIGNED,
  IN  p_restored_by BIGINT UNSIGNED,
  OUT p_status_code VARCHAR(30),
  OUT p_message VARCHAR(255)
)
sp_folder_restore: BEGIN
  DECLARE v_parent_folder_id BIGINT UNSIGNED;
  DECLARE v_exists INT DEFAULT 0;
  DECLARE v_parent_deleted INT DEFAULT 0;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN ROLLBACK; SET p_status_code = 'INTERNAL_ERROR', p_message = 'Unexpected database error while restoring folder.'; END;
  START TRANSACTION;

  SELECT COUNT(*), MAX(parent_folder_id) INTO v_exists, v_parent_folder_id
    FROM folders WHERE id = p_folder_id AND deleted_at IS NOT NULL FOR UPDATE;
  IF v_exists = 0 THEN
    ROLLBACK; SET p_status_code = 'FOLDER_NOT_FOUND', p_message = 'Folder does not exist or is not in trash.'; LEAVE sp_folder_restore;
  END IF;

  IF v_parent_folder_id IS NOT NULL THEN
    SELECT COUNT(*) INTO v_parent_deleted FROM folders WHERE id = v_parent_folder_id AND deleted_at IS NOT NULL;
    IF v_parent_deleted > 0 THEN
      ROLLBACK; SET p_status_code = 'PARENT_STILL_DELETED', p_message = 'Restore the parent folder first.'; LEAVE sp_folder_restore;
    END IF;
  END IF;

  UPDATE folders SET deleted_at = NULL WHERE id = p_folder_id;
  INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details)
    VALUES (p_restored_by, 'FOLDER_RESTORED', 'FOLDER', p_folder_id, JSON_OBJECT());
  COMMIT;
  SET p_status_code = 'OK', p_message = 'Folder restored.';
END
