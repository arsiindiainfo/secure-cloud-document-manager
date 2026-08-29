CREATE PROCEDURE sp_share_link_consume(
  IN  p_token CHAR(43),
  OUT p_document_id BIGINT UNSIGNED, OUT p_permission VARCHAR(10),
  OUT p_status_code VARCHAR(30), OUT p_message VARCHAR(255)
)
sp_share_link_consume: BEGIN
  DECLARE v_expires_at DATETIME; DECLARE v_revoked_at DATETIME;
  DECLARE v_max INT UNSIGNED; DECLARE v_count INT UNSIGNED; DECLARE v_found INT DEFAULT 0;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN ROLLBACK; SET p_status_code = 'INTERNAL_ERROR', p_message = 'Unexpected database error while resolving link.'; END;
  START TRANSACTION;
  SELECT COUNT(*) INTO v_found FROM share_links WHERE token = p_token FOR UPDATE;
  IF v_found = 0 THEN
    ROLLBACK; SET p_status_code = 'NOT_FOUND', p_message = 'This link is invalid.'; LEAVE sp_share_link_consume;
  END IF;
  SELECT document_id, permission, expires_at, revoked_at, max_downloads, download_count
    INTO p_document_id, p_permission, v_expires_at, v_revoked_at, v_max, v_count
    FROM share_links WHERE token = p_token;
  IF v_revoked_at IS NOT NULL THEN
    ROLLBACK; SET p_status_code = 'REVOKED', p_message = 'This link has been revoked.'; LEAVE sp_share_link_consume;
  ELSEIF v_expires_at < NOW() THEN
    ROLLBACK; SET p_status_code = 'EXPIRED', p_message = 'This link has expired.'; LEAVE sp_share_link_consume;
  ELSEIF v_max IS NOT NULL AND v_count >= v_max THEN
    ROLLBACK; SET p_status_code = 'LIMIT_REACHED', p_message = 'This link has reached its download limit.'; LEAVE sp_share_link_consume;
  END IF;
  UPDATE share_links SET download_count = download_count + 1 WHERE token = p_token;
  COMMIT;
  SET p_status_code = 'OK', p_message = 'Link valid.';
END
