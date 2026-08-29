CREATE PROCEDURE sp_user_authenticate(
  IN  p_email VARCHAR(190),
  OUT p_user_id BIGINT UNSIGNED,
  OUT p_password_hash VARCHAR(255),
  OUT p_role VARCHAR(20),
  OUT p_status VARCHAR(20),
  OUT p_status_code VARCHAR(30),
  OUT p_message VARCHAR(255)
)
sp_user_authenticate: BEGIN
  DECLARE v_found INT DEFAULT 0;
  SELECT COUNT(*) INTO v_found FROM users WHERE email = p_email AND deleted_at IS NULL;
  IF v_found = 0 THEN
    -- Deliberately the same status code the caller maps to a generic 401 —
    -- never reveals whether the email exists (§15, §14).
    SET p_status_code = 'NOT_FOUND', p_message = 'No such account.';
    LEAVE sp_user_authenticate;
  END IF;
  SELECT id, password_hash, role, status
    INTO p_user_id, p_password_hash, p_role, p_status
    FROM users WHERE email = p_email AND deleted_at IS NULL;
  SET p_status_code = 'OK', p_message = 'Account found.';
END
