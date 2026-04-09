-- Site sync transport mode
ALTER TABLE sites
    ADD COLUMN transport VARCHAR(16) NOT NULL DEFAULT 'direct_db' AFTER db_alias
    COMMENT 'direct_db = Database::connection, http_agent = REST API via papir_agent';