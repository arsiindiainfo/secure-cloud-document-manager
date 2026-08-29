CREATE PROCEDURE sp_folder_rename_move(
  IN  p_folder_id BIGINT UNSIGNED,
  IN  p_new_name VARCHAR(180),
  IN  p_new_parent_folder_id BIGINT UNSIGNED,
  IN  p_updated_by BIGINT UNSIGNED,
  OUT p_status_code VARCHAR(30),
  OUT p_message VARCHAR(255)
)
sp_folder_rename_move: BEGIN
  DECLARE v_exists INT DEFAULT 0;
  DECLARE v_parent_exists INT DEFAULT 0;
  DECLARE v_is_cycle INT DEFAULT 0;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN ROLLBACK; SET p_status_code = 'INTERNAL_ERROR', p_message = 'Unexpected database error while renaming/moving folder.'; END;
  START TRANSACTION;

  SELECT COUNT(*) INTO v_exists FROM folders WHERE id = p_folder_id AND deleted_at IS NULL FOR UPDATE;
  IF v_exists = 0 THEN
    ROLLBACK; SET p_status_code = 'FOLDER_NOT_FOUND', p_message = 'Folder does not exist or is deleted.'; LEAVE sp_folder_rename_move;
  END IF;

  IF p_new_parent_folder_id IS NOT NULL THEN
    IF p_new_parent_folder_id = p_folder_id THEN
      ROLLBACK; SET p_status_code = 'CYCLE_DETECTED', p_message = 'A folder cannot be moved into itself.'; LEAVE sp_folder_rename_move;
    END IF;

    SELECT COUNT(*) INTO v_parent_exists FROM folders WHERE id = p_new_parent_folder_id AND deleted_at IS NULL;
    IF v_parent_exists = 0 THEN
      ROLLBACK; SET p_status_code = 'PARENT_NOT_FOUND', p_message = 'Destination folder does not exist or is deleted.'; LEAVE sp_folder_rename_move;
    END IF;

    -- cycle check: the destination must not be a descendant of the folder being moved
    SELECT COUNT(*) INTO v_is_cycle FROM (
      WITH RECURSIVE descendants AS (
        SELECT id FROM folders WHERE parent_folder_id = p_folder_id
        UNION ALL
        SELECT f.id FROM folders f JOIN descendants d ON f.parent_folder_id = d.id
      )
      SELECT id FROM descendants WHERE id = p_new_parent_folder_id
    ) AS cycle_check;
    IF v_is_cycle > 0 THEN
      ROLLBACK; SET p_status_code = 'CYCLE_DETECTED', p_message = 'Cannot move a folder into its own descendant.'; LEAVE sp_folder_rename_move;
    END IF;
  END IF;

  IF EXISTS (
    SELECT 1 FROM folders
    WHERE name = p_new_name AND deleted_at IS NULL AND id != p_folder_id
      AND ((parent_folder_id IS NULL AND p_new_parent_folder_id IS NULL) OR parent_folder_id = p_new_parent_folder_id)
  ) THEN
    ROLLBACK; SET p_status_code = 'DUPLICATE_NAME', p_message = 'A folder with this name already exists here.'; LEAVE sp_folder_rename_move;
  END IF;

  UPDATE folders SET name = p_new_name, parent_folder_id = p_new_parent_folder_id WHERE id = p_folder_id;
  INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details)
    VALUES (p_updated_by, 'FOLDER_RENAMED_MOVED', 'FOLDER', p_folder_id, JSON_OBJECT('newName', p_new_name, 'newParentFolderId', p_new_parent_folder_id));
  COMMIT;
  SET p_status_code = 'OK', p_message = 'Folder updated.';
END
