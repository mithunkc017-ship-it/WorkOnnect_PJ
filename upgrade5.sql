-- Run if you already imported older SQL files (v6 features)
USE workerconnect;
ALTER TABLE jobs ADD urgent TINYINT DEFAULT 0, ADD commission INT DEFAULT 0;
