CREATE PROCEDURE sp_folder_hard_delete(
  IN  p_folder_id BIGINT UNSIGNED,
  IN  p_deleted_by BIGINT UNSIGNED,
  OUT p_affected_count INT UNSIGNED,
  OUT p_status_code VARCHAR(30),
  OUT p_message VARCHAR(255)
)
sp_folder_hard_delete: BEGIN
  DECLARE v_deleted_at DATETIME;
  DECLARE v_found INT DEFAULT 0;
  DECLARE v_folder_count INT UNSIGNED DEFAULT 0;
  DECLARE v_document_count INT UNSIGNED DEFAULT 0;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    SET FOREIGN_KEY_CHECKS = 1;
    ROLLBACK;
    SET p_status_code = 'INTERNAL_ERROR', p_message = 'Unexpected database error while purging folder.';
  END;
  START TRANSACTION;

  SELECT COUNT(*) INTO v_found FROM folders WHERE id = p_folder_id FOR UPDATE;
  IF v_found = 0 THEN
    ROLLBACK; SET p_status_code = 'FOLDER_NOT_FOUND', p_message = 'Folder does not exist.'; LEAVE sp_folder_hard_delete;
  END IF;

  SELECT deleted_at INTO v_deleted_at FROM folders WHERE id = p_folder_id;
  IF v_deleted_at IS NULL THEN
    ROLLBACK; SET p_status_code = 'NOT_IN_TRASH', p_message = 'Folder must be moved to trash before it can be permanently deleted.'; LEAVE sp_folder_hard_delete;
  END IF;

  -- Same subtree walk as sp_folder_soft_delete — everything under an
  -- already-trashed folder was cascaded into trash with it, so this whole
  -- subtree is safe to purge together.
  DROP TEMPORARY TABLE IF EXISTS tmp_folder_subtree;
  CREATE TEMPORARY TABLE tmp_folder_subtree (id BIGINT UNSIGNED PRIMARY KEY);
  INSERT INTO tmp_folder_subtree (id)
    WITH RECURSIVE subtree AS (
      SELECT id FROM folders WHERE id = p_folder_id
      UNION ALL
      SELECT f.id FROM folders f JOIN subtree s ON f.parent_folder_id = s.id
    )
    SELECT id FROM subtree;

  SELECT COUNT(*) INTO v_document_count FROM documents WHERE folder_id IN (SELECT id FROM tmp_folder_subtree);
  SELECT COUNT(*) INTO v_folder_count FROM tmp_folder_subtree;

  INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details)
    VALUES (p_deleted_by, 'FOLDER_PURGED', 'FOLDER', p_folder_id,
            JSON_OBJECT('foldersAffected', v_folder_count, 'documentsAffected', v_document_count));

  -- fk_versions_document is RESTRICT — versions before documents.
  DELETE dv FROM document_versions dv
    JOIN documents d ON d.id = dv.document_id
    WHERE d.folder_id IN (SELECT id FROM tmp_folder_subtree);

  -- fk_documents_folder is RESTRICT — documents before their folder.
  DELETE FROM documents WHERE folder_id IN (SELECT id FROM tmp_folder_subtree);

  -- folders.parent_folder_id is a self-referencing RESTRICT FK, so a
  -- single multi-row DELETE across parents and children in the same
  -- statement isn't guaranteed a safe order. The whole subtree is already
  -- precisely scoped by the temp table, so disabling checks for just this
  -- one statement (reset immediately after, and in the exception handler
  -- too) is safe rather than looping the delete by depth.
  SET FOREIGN_KEY_CHECKS = 0;
  DELETE FROM folders WHERE id IN (SELECT id FROM tmp_folder_subtree);
  SET FOREIGN_KEY_CHECKS = 1;

  DROP TEMPORARY TABLE IF EXISTS tmp_folder_subtree;
  SET p_affected_count = v_folder_count + v_document_count;

  COMMIT;
  SET p_status_code = 'OK', p_message = 'Folder permanently deleted.';
END
