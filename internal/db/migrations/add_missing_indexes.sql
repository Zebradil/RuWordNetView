-- Missing indexes identified by EXPLAIN ANALYZE against the production schema.
-- Apply with: psql -U <user> -d <db> -f add_missing_indexes.sql

-- CRITICAL: senses table has 154k rows; synset_id lookup is a full seq scan (~27ms).
-- Used by querySynsetSenses (direct synsets) and querySynsetRelations (child synsets).
CREATE INDEX CONCURRENTLY IF NOT EXISTS senses_synset_id_idx ON senses (synset_id);

-- ILI UNION query rewrite: push concept_id filter into each branch of the UNION so
-- the planner can use the existing PK (concept_id, wn_id, source) instead of scanning
-- all 13k+ approved rows. See repo.go buildILIBatch for the updated query.
--
-- The non-manual branch already benefits from the PK leading column (concept_id).
-- The manual branch joins wn_mapping on wn31; a covering index avoids the 117k-row
-- hash scan on wn_mapping.
CREATE INDEX CONCURRENTLY IF NOT EXISTS wn_mapping_wn31_wn30_idx ON wn_mapping (wn31, wn30);

-- ili_map_wn: queries filter on (wn, version=30); current index is on wn alone.
-- A compound index lets Postgres satisfy both predicates from the index.
CREATE INDEX CONCURRENTLY IF NOT EXISTS ili_map_wn_wn_version_idx ON ili_map_wn (wn, version);
