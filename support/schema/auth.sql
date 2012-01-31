BEGIN;

CREATE SCHEMA auth;

CREATE TABLE auth.user_shadows (
	user_id int4 NOT NULL PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
	login_password varchar(512) DEFAULT NULL,
	last_change_days int NOT NULL DEFAULT CURRENT_DATE - DATE '1970-01-01',
	min_change_days int NOT NULL DEFAULT '0',
	max_change_days int NOT NULL DEFAULT '99999',
	warn_days int4 NOT NULL DEFAULT '7',
	disable_inactive_days int4 DEFAULT '7',
	expiration_days int DEFAULT '36500',
	account_expired boolean NOT NULL DEFAULT FALSE,
	request_new_password boolean NOT NULL DEFAULT FALSE,
	status varchar(16) NOT NULL DEFAULT 'Active' CHECK(status IN('Active', 'Inactive', 'System', 'Disabled')),
	date_created timestamp DEFAULT CURRENT_TIMESTAMP,
	date_last_accessed timestamp DEFAULT NULL,
	last_accessed_from varchar(45) DEFAULT NULL
);

CREATE TABLE auth.user_email_addresses (
	user_id int4 NOT NULL REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
	email_address varchar(128) NOT NULL PRIMARY KEY
);

CREATE TABLE auth.user_public_keys (
	id serial PRIMARY KEY,
	user_id int4 NOT NULL REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
	public_key varchar(4096) NOT NULL
);

CREATE TABLE auth.user_sessions (
	id varchar(64) PRIMARY KEY,
	user_id integer NOT NULL REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
	last_activity timestamp NOT NULL,
	remote_address varchar(16) NOT NULL,
	rebuild_acl boolean DEFAULT TRUE
);

CREATE TABLE auth.login_attempts (
	user_id int4 REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
	remote_address varchar(45) NOT NULL, /* Supports IPv6 and possible IPv4 tunneling representation */
	date_occurred timestamp DEFAULT CURRENT_TIMESTAMP,
	UNIQUE (user_id, remote_address, date_occurred),
	PRIMARY KEY (remote_address, date_occurred)
);

COMMIT;
