CREATE PROCEDURE sp_document_search(
  IN  p_user_id BIGINT UNSIGNED,
  IN  p_is_admin TINYINT(1),
  IN  p_search VARCHAR(255),
  IN  p_folder_id BIGINT UNSIGNED,
  IN  p_mime_type VARCHAR(150),
  IN  p_sort VARCHAR(30),
  IN  p_direction VARCHAR(4),
  IN  p_page INT UNSIGNED,
  IN  p_limit INT UNSIGNED,
  OUT p_total_count BIGINT UNSIGNED
)
sp_document_search: BEGIN
  DECLARE v_offset INT UNSIGNED;
  SET v_offset = (p_page - 1) * p_limit;

  -- Visibility set: every document this user may even learn exists — a
  -- direct grant, or a grant on any ancestor folder at any depth (§6.3:
  -- folder inheritance is the floor, never a ceiling, so "any qualifying
  -- grant path" is exactly the correct visibility test). ADMIN bypasses
  -- entirely. This does not resolve *which* permission level applies —
  -- that precision lives in DocumentService::authorize() (§5) — it only
  -- decides what can appear in a list at all.
  DROP TEMPORARY TABLE IF EXISTS tmp_accessible_documents;
  CREATE TEMPORARY TABLE tmp_accessible_documents (document_id BIGINT UNSIGNED PRIMARY KEY);

  IF p_is_admin = 1 THEN
    INSERT INTO tmp_accessible_documents (document_id)
      SELECT id FROM documents WHERE deleted_at IS NULL;
  ELSE
    INSERT IGNORE INTO tmp_accessible_documents (document_id)
      SELECT dp.document_id
      FROM document_permissions dp
      JOIN documents d ON d.id = dp.document_id AND d.deleted_at IS NULL
      WHERE dp.user_id = p_user_id AND dp.document_id IS NOT NULL;

    INSERT IGNORE INTO tmp_accessible_documents (document_id)
      SELECT doc.id
      FROM documents doc
      JOIN (
        WITH RECURSIVE granted_folders AS (
          SELECT folder_id AS id FROM document_permissions
            WHERE user_id = p_user_id AND folder_id IS NOT NULL
          UNION ALL
          SELECT f.id FROM folders f
          JOIN granted_folders gf ON f.parent_folder_id = gf.id
          WHERE f.deleted_at IS NULL
        )
        SELECT id FROM granted_folders
      ) af ON af.id = doc.folder_id
      WHERE doc.deleted_at IS NULL;
  END IF;

  SELECT COUNT(*) INTO p_total_count
  FROM tmp_accessible_documents t
  JOIN documents d ON d.id = t.document_id
  JOIN document_versions v ON v.document_id = d.id AND v.is_current = 1
  WHERE (p_folder_id IS NULL OR d.folder_id = p_folder_id)
    AND (p_mime_type IS NULL OR v.mime_type = p_mime_type)
    AND (
      p_search IS NULL OR p_search = ''
      OR MATCH(d.name, d.description, d.tags) AGAINST (CONCAT(p_search, '*') IN BOOLEAN MODE)
    );

  SELECT
    d.id, d.name, d.description, d.tags, d.folder_id, f.name AS folder_name,
    d.current_version, v.mime_type, v.size_bytes, v.thumbnail_s3_key,
    d.created_by, d.created_at, d.updated_at
  FROM tmp_accessible_documents t
  JOIN documents d ON d.id = t.document_id
  JOIN folders f ON f.id = d.folder_id
  JOIN document_versions v ON v.document_id = d.id AND v.is_current = 1
  WHERE (p_folder_id IS NULL OR d.folder_id = p_folder_id)
    AND (p_mime_type IS NULL OR v.mime_type = p_mime_type)
    AND (
      p_search IS NULL OR p_search = ''
      OR MATCH(d.name, d.description, d.tags) AGAINST (CONCAT(p_search, '*') IN BOOLEAN MODE)
    )
  ORDER BY
    CASE WHEN p_sort = 'name' AND p_direction = 'asc' THEN d.name END ASC,
    CASE WHEN p_sort = 'name' AND p_direction = 'desc' THEN d.name END DESC,
    CASE WHEN p_sort = 'size' AND p_direction = 'asc' THEN v.size_bytes END ASC,
    CASE WHEN p_sort = 'size' AND p_direction = 'desc' THEN v.size_bytes END DESC,
    CASE WHEN p_sort = 'updatedAt' AND p_direction = 'asc' THEN d.updated_at END ASC,
    CASE WHEN p_sort = 'updatedAt' AND p_direction = 'desc' THEN d.updated_at END DESC,
    d.updated_at DESC
  LIMIT p_limit OFFSET v_offset;

  DROP TEMPORARY TABLE IF EXISTS tmp_accessible_documents;
END
