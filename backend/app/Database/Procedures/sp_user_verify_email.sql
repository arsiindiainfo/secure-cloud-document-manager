CREATE PROCEDURE sp_user_verify_email(
  IN  p_token CHAR(43),
  OUT p_user_id BIGINT UNSIGNED,
  OUT p_status_code VARCHAR(30),
  OUT p_message VARCHAR(255)
)
sp_user_verify_email: BEGIN
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN ROLLBACK; SET p_status_code = 'INTERNAL_ERROR', p_message = 'Unexpected database error while verifying email.'; END;
  START TRANSACTION;

  SELECT id INTO p_user_id FROM users
    WHERE email_verification_token = p_token AND email_verified_at IS NULL
    FOR UPDATE;
  IF p_user_id IS NULL THEN
    ROLLBACK; SET p_status_code = 'INVALID_TOKEN', p_message = 'This verification link is invalid or has already been used.'; LEAVE sp_user_verify_email;
  END IF;

  UPDATE users SET email_verified_at = NOW(), email_verification_token = NULL WHERE id = p_user_id;
  INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details)
    VALUES (p_user_id, 'USER_EMAIL_VERIFIED', 'USER', p_user_id, JSON_OBJECT());
  COMMIT;
  SET p_status_code = 'OK', p_message = 'Email verified.';
END
