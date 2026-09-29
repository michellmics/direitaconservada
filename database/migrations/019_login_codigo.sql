-- =====================================================================
-- Login por código de 6 dígitos enviado por e-mail (no lugar do link mágico).
--   token_hash  HMAC do código; a busca é pelo usuário, e dois códigos iguais podem existir (o índice único sai)
--   tentativas  códigos errados digitados; no limite (includes/auth.php) o código morre
-- =====================================================================

ALTER TABLE login_tokens
    DROP INDEX uq_login_tokens_hash,
    ADD COLUMN tentativas TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER usado_em;
