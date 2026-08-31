CREATE PROCEDURE sp_document_permission_grant(
  IN  p_document_id BIGINT UNSIGNED,
  IN  p_user_id BIGINT UNSIGNED,
  IN  p_permission VARCHAR(10),
  IN  p_granted_by BIGINT UNSIGNED,
  OUT p_status_code VARCHAR(30),
  OUT p_message VARCHAR(255)
)
sp_document_permission_grant: BEGIN
  DECLARE v_document_exists INT DEFAULT 0;
  DECLARE v_user_exists INT DEFAULT 0;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN ROLLBACK; SET p_status_code = 'INTERNAL_ERROR', p_message = 'Unexpected database error while granting access.'; END;
  START TRANSACTION;

  SELECT COUNT(*) INTO v_document_exists FROM documents WHERE id = p_document_id AND deleted_at IS NULL;
  IF v_document_exists = 0 THEN
    ROLLBACK; SET p_status_code = 'DOCUMENT_NOT_FOUND', p_message = 'Document does not exist or is deleted.'; LEAVE sp_document_permission_grant;
  END IF;

  SELECT COUNT(*) INTO v_user_exists FROM users WHERE id = p_user_id AND deleted_at IS NULL;
  IF v_user_exists = 0 THEN
    ROLLBACK; SET p_status_code = 'USER_NOT_FOUND', p_message = 'User does not exist.'; LEAVE sp_document_permission_grant;
  END IF;

  INSERT INTO document_permissions (document_id, user_id, permission, granted_by)
    VALUES (p_document_id, p_user_id, p_permission, p_granted_by)
    ON DUPLICATE KEY UPDATE permission = p_permission, granted_by = p_granted_by, granted_at = NOW();

  INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details)
    VALUES (p_granted_by, 'DOCUMENT_PERMISSION_GRANTED', 'DOCUMENT', p_document_id, JSON_OBJECT('granteeUserId', p_user_id, 'permission', p_permission));
  COMMIT;
  SET p_status_code = 'OK', p_message = 'Access granted.';
END
