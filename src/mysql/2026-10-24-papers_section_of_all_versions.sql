-- Moving a paper to another section used to update its current version only.
-- Every version of a paper now takes the section of its latest version, in the same journal.
UPDATE `PAPERS` p
    JOIN (SELECT `PAPERID`, `RVID`, `SID`
          FROM (SELECT `PAPERID`,
                       `RVID`,
                       `SID`,
                       ROW_NUMBER() OVER (PARTITION BY `PAPERID`, `RVID` ORDER BY `DOCID` DESC) AS rn
                FROM `PAPERS`) AS versions
          WHERE rn = 1) AS latest
    ON latest.`PAPERID` = p.`PAPERID` AND latest.`RVID` = p.`RVID`
SET p.`SID` = latest.`SID`
WHERE p.`SID` <> latest.`SID`;
