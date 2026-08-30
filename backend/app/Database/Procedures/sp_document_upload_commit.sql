CREATE PROCEDURE sp_document_upload_commit(
  IN  p_folder_id BIGINT UNSIGNED,
  IN  p_name VARCHAR(200),
  IN  p_description VARCHAR(500),
  IN  p_tags VARCHAR(255),
  IN  p_s3_bucket VARCHAR(120),
  IN  p_s3_key VARCHAR(400),
  IN  p_mime_type VARCHAR(150),
  IN  p_size_bytes BIGINT UNSIGNED,
  IN  p_checksum CHAR(64),
  IN  p_created_by BIGINT UNSIGNED,
  OUT p_document_id BIGINT UNSIGNED,
  OUT p_version_id BIGINT UNSIGNED,
  OUT p_status_code VARCHAR(30),
  OUT p_message VARCHAR(255)
)
sp_document_upload_commit: BEGIN
  DECLARE v_folder_exists INT DEFAULT 0;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN ROLLBACK; SET p_status_code = 'INTERNAL_ERROR', p_message = 'Unexpected database error while committing upload.'; END;
  START TRANSACTION;

  SELECT COUNT(*) INTO v_folder_exists FROM folders WHERE id = p_folder_id AND deleted_at IS NULL FOR UPDATE;
  IF v_folder_exists = 0 THEN
    ROLLBACK; SET p_status_code = 'FOLDER_NOT_FOUND', p_message = 'Target folder does not exist or is deleted.'; LEAVE sp_document_upload_commit;
  END IF;

  IF EXISTS (SELECT 1 FROM documents WHERE folder_id = p_folder_id AND name = p_name AND deleted_at IS NULL) THEN
    ROLLBACK; SET p_status_code = 'DUPLICATE_NAME', p_message = 'A document with this name already exists in this folder.'; LEAVE sp_document_upload_commit;
  END IF;

  INSERT INTO documents (folder_id, name, description, tags, current_version, created_by)
    VALUES (p_folder_id, p_name, p_description, p_tags, 1, p_created_by);
  SET p_document_id = LAST_INSERT_ID();

  INSERT INTO document_versions (document_id, version_no, s3_bucket, s3_key, mime_type, size_bytes, checksum_sha256, is_current, uploaded_by)
    VALUES (p_document_id, 1, p_s3_bucket, p_s3_key, p_mime_type, p_size_bytes, p_checksum, 1, p_created_by);
  SET p_version_id = LAST_INSERT_ID();

  -- the uploader is auto-granted OWNER on the document itself (§6.3) —
  -- explicit, not just inherited from the folder, so it survives a later
  -- move or a folder-level grant change
  INSERT INTO document_permissions (document_id, user_id, permission, granted_by)
    VALUES (p_document_id, p_created_by, 'OWNER', p_created_by);

  INSERT INTO document_processing_jobs (document_version_id, status) VALUES (p_version_id, 'PENDING');

  INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details)
    VALUES (p_created_by, 'DOCUMENT_UPLOADED', 'DOCUMENT', p_document_id, JSON_OBJECT('name', p_name, 'folderId', p_folder_id));
  COMMIT;
  SET p_status_code = 'OK', p_message = 'Document created.';
END
