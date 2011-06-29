CREATE TABLE users (
	id serial PRIMARY KEY,
	username varchar(32) NOT NULL UNIQUE,
	password varchar(512) NOT NULL,
	name varchar(64) NOT NULL,
	uid 
);

CREATE TABLE user_public_keys (
	id serial PRIMARY KEY,
	user_id integer NOT NULL REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
	public_key text NOT NULL
);

CREATE TABLE user_settings (
	user_id integer PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
	spam_level float NOT NULL default 6.0
);

CREATE TABLE domains (
	id serial PRIMARY KEY,
	parent_id integer REFERENCES domains(id) ON DELETE SET NULL ON UPDATE CASCADE,
	name varchar(256) NOT NULL,
	description varchar(256) NOT NULL,
	owner integer NOT NULL REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,
);

CREATE TABLE domain_aliases (
	domain_id integer NOT NULL REFERENCES domains(id) ON DELETE CASCADE ON UPDATE CASCADE,
	alias varchar(256) NOT NULL,
	PRIMARY KEY (domain_id, alias)
);

CREATE TABLE domain_mail_settings (
	domain_id integer PRIMARY KEY REFERENCES domains(id) ON DELETE CASCADE ON UPDATE CASCADE,
	mailboxes integer NOT NULL DEFAULT 0,
	quota integer NOT NULL DEFAULT 0,
);

CREATE TABLE domain_web_settings (
	domain_id integer PRIMARY KEY REFERENCES domains(id) ON DELETE CASCADE ON UPDATE CASCADE,
	quota integer NOT NULL DEFAULT 0,
	enable_php boolean NOT NULL DEFAULT FALSE,
	enable_asp boolean NOT NULL DEFAULT FALSE
);

CREATE TABLE domain_users (
	id serial PRIMARY KEY,
	user_id integer NOT NULL REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
	domain_id integer NOT NULL REFERENCES domains(id) ON DELETE CASCADE ON UPDATE CASCADE,
	alias varchar(32) NOT NULL,
	password varchar(512) NOT NULL,
	UNIQUE (alias, domain_id)
);

CREATE TABLE domain_user_settings (
	domain_user_id integer PRIMARY KEY REFERENCES domain_users(id) ON DELETE CASCADE ON UPDATE CASCADE,
	can_write boolean NOT NULL DEFAULT FALSE,
	spam_level float DEFAULT NULL
);

CREATE TABLE domain_user_aliases (
	domain_user_id integer NOT NULL REFERENCES domain_users(id) ON DELETE CASCADE ON UPDATE CASCADE,
	alias varchar(32) NOT NULL,
	PRIMARY KEY (domain_user_id, alias)
);
