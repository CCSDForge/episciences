
ALTER TABLE PAPER_LOG ADD COLUMN detail_json JSON;

UPDATE PAPER_LOG SET detail_json = IF(JSON_VALID(DETAIL), DETAIL, NULL);

ALTER TABLE PAPER_LOG DROP COLUMN DETAIL;

ALTER TABLE PAPER_LOG CHANGE detail_json DETAIL JSON;

-- La valeur est automatiquement calculée selon une expression définie, puis stockée physiquement dans la table au moment de l'insertion ou de la mise à jour

ALTER TABLE PAPER_LOG ADD COLUMN status INT UNSIGNED AS (JSON_UNQUOTE(JSON_EXTRACT(DETAIL, '$.status'))) STORED;


CREATE INDEX idx_status ON PAPER_LOG (status);

