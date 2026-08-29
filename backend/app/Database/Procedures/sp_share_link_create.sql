CREATE PROCEDURE sp_share_link_create(
  IN  p_document_id BIGINT UNSIGNED,
  IN  p_permission VARCHAR(10),
  IN  p_expires_at DATETIME,
  IN  p_max_downloads INT UNSIGNED,
  IN  p_created_by BIGINT UNSIGNED,
  OUT p_share_link_id BIGINT UNSIGNED,
  OUT p_token CHAR(43),
  OUT p_status_code VARCHAR(30),
  OUT p_message VARCHAR(255)
)
sp_share_link_create: BEGIN
  DECLARE v_exists INT DEFAULT 0;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN ROLLBACK; SET p_status_code = 'INTERNAL_ERROR', p_message = 'Unexpected database error while creating share link.'; END;
  START TRANSACTION;

  SELECT COUNT(*) INTO v_exists FROM documents WHERE id = p_document_id AND deleted_at IS NULL;
  IF v_exists = 0 THEN
    ROLLBACK; SET p_status_code = 'DOCUMENT_NOT_FOUND', p_message = 'Document does not exist or is deleted.'; LEAVE sp_share_link_create;
  END IF;

  -- 32 random bytes, base64url without padding -> exactly 43 chars (matches
  -- share_links.token CHAR(43)); collision probability is negligible so no
  -- retry loop is needed.
  SET p_token = REPLACE(REPLACE(REPLACE(TO_BASE64(RANDOM_BYTES(32)), '+', '-'), '/', '_'), '=', '');

  INSERT INTO share_links (document_id, token, permission, max_downloads, expires_at, created_by)
    VALUES (p_document_id, p_token, p_permission, p_max_downloads, p_expires_at, p_created_by);
  SET p_share_link_id = LAST_INSERT_ID();

  INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details)
    VALUES (p_created_by, 'SHARE_LINK_CREATED', 'SHARE_LINK', p_share_link_id,
            JSON_OBJECT('documentId', p_document_id, 'permission', p_permission, 'expiresAt', p_expires_at));
  COMMIT;
  SET p_status_code = 'OK', p_message = 'Share link created.';
END
