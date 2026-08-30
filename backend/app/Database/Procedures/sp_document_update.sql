CREATE PROCEDURE sp_document_update(
  IN  p_document_id BIGINT UNSIGNED,
  IN  p_name VARCHAR(200),
  IN  p_description VARCHAR(500),
  IN  p_tags VARCHAR(255),
  IN  p_folder_id BIGINT UNSIGNED,
  IN  p_updated_by BIGINT UNSIGNED,
  OUT p_status_code VARCHAR(30),
  OUT p_message VARCHAR(255)
)
sp_document_update: BEGIN
  DECLARE v_exists INT DEFAULT 0;
  DECLARE v_deleted_at DATETIME;
  DECLARE v_current_folder_id BIGINT UNSIGNED;
  DECLARE v_folder_exists INT DEFAULT 0;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN ROLLBACK; SET p_status_code = 'INTERNAL_ERROR', p_message = 'Unexpected database error while updating document.'; END;
  START TRANSACTION;

  SELECT COUNT(*), MAX(deleted_at), MAX(folder_id) INTO v_exists, v_deleted_at, v_current_folder_id
    FROM documents WHERE id = p_document_id FOR UPDATE;
  IF v_exists = 0 THEN
    ROLLBACK; SET p_status_code = 'DOCUMENT_NOT_FOUND', p_message = 'Document does not exist.'; LEAVE sp_document_update;
  END IF;
  IF v_deleted_at IS NOT NULL THEN
    ROLLBACK; SET p_status_code = 'DOCUMENT_DELETED', p_message = 'Cannot update a deleted document.'; LEAVE sp_document_update;
  END IF;

  IF p_folder_id <> v_current_folder_id THEN
    SELECT COUNT(*) INTO v_folder_exists FROM folders WHERE id = p_folder_id AND deleted_at IS NULL;
    IF v_folder_exists = 0 THEN
      ROLLBACK; SET p_status_code = 'FOLDER_NOT_FOUND', p_message = 'Destination folder does not exist or is deleted.'; LEAVE sp_document_update;
    END IF;
  END IF;

  IF EXISTS (
    SELECT 1 FROM documents
    WHERE folder_id = p_folder_id AND name = p_name AND deleted_at IS NULL AND id != p_document_id
  ) THEN
    ROLLBACK; SET p_status_code = 'DUPLICATE_NAME', p_message = 'A document with this name already exists in this folder.'; LEAVE sp_document_update;
  END IF;

  UPDATE documents SET name = p_name, description = p_description, tags = p_tags, folder_id = p_folder_id
    WHERE id = p_document_id;
  INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details)
    VALUES (p_updated_by, 'DOCUMENT_UPDATED', 'DOCUMENT', p_document_id, JSON_OBJECT('name', p_name, 'folderId', p_folder_id));
  COMMIT;
  SET p_status_code = 'OK', p_message = 'Document updated.';
END
