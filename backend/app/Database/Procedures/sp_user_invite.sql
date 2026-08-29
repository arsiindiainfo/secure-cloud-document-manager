CREATE PROCEDURE sp_user_invite(
  IN  p_name VARCHAR(120),
  IN  p_email VARCHAR(190),
  IN  p_role VARCHAR(20),
  IN  p_password_hash VARCHAR(255),
  IN  p_invited_by BIGINT UNSIGNED,
  OUT p_user_id BIGINT UNSIGNED,
  OUT p_status_code VARCHAR(30),
  OUT p_message VARCHAR(255)
)
sp_user_invite: BEGIN
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN ROLLBACK; SET p_status_code = 'INTERNAL_ERROR', p_message = 'Unexpected database error while inviting user.'; END;
  START TRANSACTION;
  IF EXISTS (SELECT 1 FROM users WHERE email = p_email) THEN
    ROLLBACK; SET p_status_code = 'DUPLICATE_NAME', p_message = 'This email is already registered.'; LEAVE sp_user_invite;
  END IF;
  INSERT INTO users (name, email, password_hash, role, status)
    VALUES (p_name, p_email, p_password_hash, p_role, 'ACTIVE');
  SET p_user_id = LAST_INSERT_ID();
  INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details)
    VALUES (p_invited_by, 'USER_INVITED', 'USER', p_user_id, JSON_OBJECT('email', p_email, 'role', p_role));
  COMMIT;
  SET p_status_code = 'OK', p_message = 'User invited.';
END
