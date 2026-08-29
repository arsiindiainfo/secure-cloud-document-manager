CREATE PROCEDURE sp_document_new_version(
  IN  p_document_id BIGINT UNSIGNED, IN p_s3_bucket VARCHAR(120), IN p_s3_key VARCHAR(400),
  IN  p_mime_type VARCHAR(150), IN p_size_bytes BIGINT UNSIGNED, IN p_checksum CHAR(64), IN p_uploaded_by BIGINT UNSIGNED,
  OUT p_version_id BIGINT UNSIGNED, OUT p_version_no INT UNSIGNED, OUT p_status_code VARCHAR(30), OUT p_message VARCHAR(255)
)
sp_document_new_version: BEGIN
  DECLARE v_deleted_at DATETIME;
  DECLARE v_exists INT DEFAULT 0;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN ROLLBACK; SET p_status_code = 'INTERNAL_ERROR', p_message = 'Unexpected database error while adding version.'; END;
  START TRANSACTION;
  -- lock the parent row so two concurrent uploads can never both compute the same next version_no
  SELECT COUNT(*), MAX(deleted_at) INTO v_exists, v_deleted_at FROM documents WHERE id = p_document_id FOR UPDATE;
  IF v_exists = 0 THEN
    ROLLBACK; SET p_status_code = 'DOCUMENT_NOT_FOUND', p_message = 'Document does not exist.'; LEAVE sp_document_new_version;
  END IF;
  IF v_deleted_at IS NOT NULL THEN
    ROLLBACK; SET p_status_code = 'DOCUMENT_DELETED', p_message = 'Cannot version a deleted document.'; LEAVE sp_document_new_version;
  END IF;
  UPDATE document_versions SET is_current = 0 WHERE document_id = p_document_id AND is_current = 1;
  SELECT COALESCE(MAX(version_no), 0) + 1 INTO p_version_no FROM document_versions WHERE document_id = p_document_id;
  INSERT INTO document_versions (document_id, version_no, s3_bucket, s3_key, mime_type, size_bytes, checksum_sha256, is_current, uploaded_by)
    VALUES (p_document_id, p_version_no, p_s3_bucket, p_s3_key, p_mime_type, p_size_bytes, p_checksum, 1, p_uploaded_by);
  SET p_version_id = LAST_INSERT_ID();
  UPDATE documents SET current_version = p_version_no WHERE id = p_document_id;
  INSERT INTO document_processing_jobs (document_version_id, status) VALUES (p_version_id, 'PENDING');
  INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details)
    VALUES (p_uploaded_by, 'DOCUMENT_VERSION_UPLOADED', 'DOCUMENT', p_document_id, JSON_OBJECT('versionNo', p_version_no));
  COMMIT;
  SET p_status_code = 'OK', p_message = 'Version uploaded.';
END
