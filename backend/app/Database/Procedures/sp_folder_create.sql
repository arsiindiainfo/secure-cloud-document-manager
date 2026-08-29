CREATE PROCEDURE sp_folder_create(
  IN  p_name VARCHAR(180),
  IN  p_parent_folder_id BIGINT UNSIGNED,
  IN  p_created_by BIGINT UNSIGNED,
  OUT p_folder_id BIGINT UNSIGNED,
  OUT p_status_code VARCHAR(30),
  OUT p_message VARCHAR(255)
)
sp_folder_create: BEGIN
  DECLARE v_parent_exists INT DEFAULT 0;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    SET p_status_code = 'INTERNAL_ERROR', p_message = 'Unexpected database error while creating folder.';
  END;
  START TRANSACTION;
  IF p_parent_folder_id IS NOT NULL THEN
    SELECT COUNT(*) INTO v_parent_exists FROM folders
      WHERE id = p_parent_folder_id AND deleted_at IS NULL FOR UPDATE;
    IF v_parent_exists = 0 THEN
      ROLLBACK;
      SET p_status_code = 'PARENT_NOT_FOUND', p_message = 'Parent folder does not exist or is deleted.';
      LEAVE sp_folder_create;
    END IF;
  END IF;
  IF EXISTS (
    SELECT 1 FROM folders
    WHERE name = p_name AND deleted_at IS NULL
      AND ((parent_folder_id IS NULL AND p_parent_folder_id IS NULL) OR parent_folder_id = p_parent_folder_id)
  ) THEN
    ROLLBACK;
    SET p_status_code = 'DUPLICATE_NAME', p_message = 'A folder with this name already exists here.';
    LEAVE sp_folder_create;
  END IF;
  INSERT INTO folders (name, parent_folder_id, created_by)
    VALUES (p_name, p_parent_folder_id, p_created_by);
  SET p_folder_id = LAST_INSERT_ID();
  INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details)
    VALUES (p_created_by, 'FOLDER_CREATED', 'FOLDER', p_folder_id, JSON_OBJECT('name', p_name));
  COMMIT;
  SET p_status_code = 'OK', p_message = 'Folder created.';
END
