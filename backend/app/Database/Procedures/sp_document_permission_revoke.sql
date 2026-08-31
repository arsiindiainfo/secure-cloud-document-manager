CREATE PROCEDURE sp_document_permission_revoke(
  IN  p_document_id BIGINT UNSIGNED,
  IN  p_user_id BIGINT UNSIGNED,
  IN  p_revoked_by BIGINT UNSIGNED,
  OUT p_status_code VARCHAR(30),
  OUT p_message VARCHAR(255)
)
sp_document_permission_revoke: BEGIN
  DECLARE v_permission VARCHAR(10);
  DECLARE v_owner_count INT DEFAULT 0;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN ROLLBACK; SET p_status_code = 'INTERNAL_ERROR', p_message = 'Unexpected database error while revoking access.'; END;
  START TRANSACTION;

  SELECT permission INTO v_permission FROM document_permissions
    WHERE document_id = p_document_id AND user_id = p_user_id FOR UPDATE;

  IF v_permission IS NULL THEN
    ROLLBACK; SET p_status_code = 'GRANT_NOT_FOUND', p_message = 'This user has no direct access to revoke.'; LEAVE sp_document_permission_revoke;
  END IF;

  IF v_permission = 'OWNER' THEN
    SELECT COUNT(*) INTO v_owner_count FROM document_permissions
      WHERE document_id = p_document_id AND permission = 'OWNER' FOR UPDATE;
    IF v_owner_count <= 1 THEN
      ROLLBACK; SET p_status_code = 'LAST_OWNER', p_message = 'Cannot revoke the last remaining owner.'; LEAVE sp_document_permission_revoke;
    END IF;
  END IF;

  DELETE FROM document_permissions WHERE document_id = p_document_id AND user_id = p_user_id;

  INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details)
    VALUES (p_revoked_by, 'DOCUMENT_PERMISSION_REVOKED', 'DOCUMENT', p_document_id, JSON_OBJECT('granteeUserId', p_user_id, 'permission', v_permission));
  COMMIT;
  SET p_status_code = 'OK', p_message = 'Access revoked.';
END
