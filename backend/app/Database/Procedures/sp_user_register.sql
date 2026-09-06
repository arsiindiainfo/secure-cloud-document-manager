CREATE PROCEDURE sp_user_register(
  IN  p_name VARCHAR(120),
  IN  p_email VARCHAR(190),
  IN  p_password_hash VARCHAR(255),
  IN  p_role VARCHAR(20),
  IN  p_verification_token CHAR(43),
  OUT p_user_id BIGINT UNSIGNED,
  OUT p_status_code VARCHAR(30),
  OUT p_message VARCHAR(255)
)
sp_user_register: BEGIN
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN ROLLBACK; SET p_status_code = 'INTERNAL_ERROR', p_message = 'Unexpected database error while registering.'; END;
  START TRANSACTION;
  IF EXISTS (SELECT 1 FROM users WHERE email = p_email) THEN
    ROLLBACK; SET p_status_code = 'DUPLICATE_NAME', p_message = 'This email is already registered.'; LEAVE sp_user_register;
  END IF;
  -- email_verified_at stays NULL — this is the one path that requires the
  -- verify-by-email step before login (§15).
  INSERT INTO users (name, email, password_hash, role, status, email_verification_token)
    VALUES (p_name, p_email, p_password_hash, p_role, 'ACTIVE', p_verification_token);
  SET p_user_id = LAST_INSERT_ID();
  INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details)
    VALUES (p_user_id, 'USER_REGISTERED', 'USER', p_user_id, JSON_OBJECT('email', p_email));
  COMMIT;
  SET p_status_code = 'OK', p_message = 'Registered.';
END
