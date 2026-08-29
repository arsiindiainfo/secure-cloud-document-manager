CREATE PROCEDURE sp_folder_soft_delete(
  IN  p_folder_id BIGINT UNSIGNED,
  IN  p_deleted_by BIGINT UNSIGNED,
  OUT p_affected_count INT UNSIGNED,
  OUT p_status_code VARCHAR(30),
  OUT p_message VARCHAR(255)
)
sp_folder_soft_delete: BEGIN
  DECLARE v_exists INT DEFAULT 0;
  DECLARE v_folder_count INT UNSIGNED DEFAULT 0;
  DECLARE v_document_count INT UNSIGNED DEFAULT 0;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN ROLLBACK; SET p_status_code = 'INTERNAL_ERROR', p_message = 'Unexpected database error while deleting folder.'; END;
  START TRANSACTION;

  SELECT COUNT(*) INTO v_exists FROM folders WHERE id = p_folder_id AND deleted_at IS NULL FOR UPDATE;
  IF v_exists = 0 THEN
    ROLLBACK; SET p_status_code = 'FOLDER_NOT_FOUND', p_message = 'Folder does not exist or is already deleted.'; LEAVE sp_folder_soft_delete;
  END IF;

  DROP TEMPORARY TABLE IF EXISTS tmp_folder_subtree;
  CREATE TEMPORARY TABLE tmp_folder_subtree (id BIGINT UNSIGNED PRIMARY KEY);
  INSERT INTO tmp_folder_subtree (id)
    WITH RECURSIVE subtree AS (
      SELECT id FROM folders WHERE id = p_folder_id
      UNION ALL
      SELECT f.id FROM folders f JOIN subtree s ON f.parent_folder_id = s.id WHERE f.deleted_at IS NULL
    )
    SELECT id FROM subtree;

  UPDATE folders SET deleted_at = NOW() WHERE id IN (SELECT id FROM tmp_folder_subtree) AND deleted_at IS NULL;
  SET v_folder_count = ROW_COUNT();

  UPDATE documents SET deleted_at = NOW() WHERE folder_id IN (SELECT id FROM tmp_folder_subtree) AND deleted_at IS NULL;
  SET v_document_count = ROW_COUNT();

  DROP TEMPORARY TABLE IF EXISTS tmp_folder_subtree;
  SET p_affected_count = v_folder_count + v_document_count;

  INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details)
    VALUES (p_deleted_by, 'FOLDER_DELETED', 'FOLDER', p_folder_id,
            JSON_OBJECT('foldersAffected', v_folder_count, 'documentsAffected', v_document_count));
  COMMIT;
  SET p_status_code = 'OK', p_message = 'Folder moved to trash.';
END
