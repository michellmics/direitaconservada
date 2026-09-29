-- =====================================================================
-- Login por código de 6 dígitos enviado por e-mail (no lugar do link mágico).
--   token_hash  HMAC do código; a busca é pelo usuário, e dois códigos iguais podem existir (o índice único sai)
--   tentativas  códigos errados digitados; no limite (includes/auth.php) o código morre
-- Feita para rodar em qualquer banco: o índice único de token_hash pode ter outro nome (ou nem existir)
-- e a coluna só é criada se ainda não existir.
-- =====================================================================

SET @idx = (SELECT MIN(index_name) FROM information_schema.statistics
            WHERE table_schema = DATABASE() AND table_name = 'login_tokens' AND column_name = 'token_hash' AND non_unique = 0);
SET @sql = IF(@idx IS NULL, 'DO 0', CONCAT('ALTER TABLE login_tokens DROP INDEX `', @idx, '`'));
PREPARE s FROM @sql;
EXECUTE s;
DEALLOCATE PREPARE s;

SET @sql = IF(EXISTS (SELECT 1 FROM information_schema.columns
                      WHERE table_schema = DATABASE() AND table_name = 'login_tokens' AND column_name = 'tentativas'),
              'DO 0',
              'ALTER TABLE login_tokens ADD COLUMN tentativas TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER usado_em');
PREPARE s FROM @sql;
EXECUTE s;
DEALLOCATE PREPARE s;
