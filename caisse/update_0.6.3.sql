ALTER TABLE @PREFIX_sessions RENAME TO @PREFIX_sessions_old;

CREATE TABLE IF NOT EXISTS @PREFIX_sessions (
	-- Cash register sessions
	id INTEGER NOT NULL PRIMARY KEY,
	opened TEXT NOT NULL DEFAULT (datetime('now','localtime')),
	closed TEXT NULL,
	open_user TEXT NULL,
	open_amount INTEGER NULL,
	close_amount INTEGER NULL,
	close_user TEXT NULL,
	error_amount INTEGER NULL
);

INSERT INTO @PREFIX_sessions SELECT * FROM @PREFIX_sessions_old;
DROP TABLE @PREFIX_sessions_old;

UPDATE @PREFIX_sessions SET open_user = (SELECT @__NAME FROM users WHERE id = open_user) WHERE open_user IS NOT NULL;
UPDATE @PREFIX_sessions SET close_user = (SELECT @__NAME FROM users WHERE id = close_user) WHERE close_user IS NOT NULL;

