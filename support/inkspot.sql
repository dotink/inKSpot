BEGIN;

CREATE SEQUENCE group_id MINVALUE 10000 MAXVALUE 2147483647 NO CYCLE;
CREATE SEQUENCE user_id MINVALUE 10000 MAXVALUE 2147483647 NO CYCLE;

CREATE TABLE groups (
	id int4 NOT NULL PRIMARY KEY DEFAULT nextval('group_id'),
	groupname varchar(64) NOT NULL,
	description text DEFAULT NULL,
	login_password varchar(512) DEFAULT 'x'
);

CREATE TABLE users (
	id int4 NOT NULL PRIMARY KEY DEFAULT nextval('user_id'),
	username varchar(32) NOT NULL UNIQUE,
	group_id int4 NOT NULL REFERENCES groups(id) ON DELETE RESTRICT ON UPDATE CASCADE,
	description varchar(512) NOT NULL DEFAULT ',,,',
	home varchar(512) NOT NULL,
	shell varchar(512) NOT NULL DEFAULT '/usr/bin/rssh'
);

CREATE TABLE user_shadows (
	username varchar(32) NOT NULL PRIMARY KEY REFERENCES users(username) ON DELETE CASCADE ON UPDATE CASCADE,
	email_address varchar(256) NOT NULL,
	login_password varchar(512) DEFAULT NULL,
	last_change_days int NOT NULL DEFAULT CURRENT_DATE - DATE '1970-01-01',
	min_change_days int NOT NULL DEFAULT '0',
	max_change_days int NOT NULL DEFAULT '9999',
	warn_days int4 NOT NULL DEFAULT '7',
	disable_inactive_days int4 DEFAULT '7',
	expiration_days int DEFAULT '36500',
	account_expired boolean NOT NULL DEFAULT FALSE,
	request_new_password boolean NOT NULL DEFAULT FALSE
);

CREATE TABLE user_groups (
	group_id int4 NOT NULL REFERENCES groups(id) ON DELETE CASCADE ON UPDATE CASCADE,
	user_id int4 NOT NULL REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
	PRIMARY KEY (group_id, user_id)
);

CREATE TABLE user_settings (
	user_id integer PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
	spam_level float NOT NULL DEFAULT '6.0'
);

CREATE TABLE user_friends (
	user_id integer NOT NULL REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
	friend_id integer NOT NULL REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
	PRIMARY KEY (user_id, friend_id)
);

CREATE TABLE user_public_keys (
	id serial PRIMARY KEY,
	user_id integer NOT NULL REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
	description varchar(64) NOT NULL,
	public_key text NOT NULL
);

CREATE TABLE domains (
	id serial PRIMARY KEY,
	domain varchar(256) NOT NULL UNIQUE,
	description varchar(256) NOT NULL,
	alias_for varchar(256) REFERENCES domains(domain) ON DELETE CASCADE ON UPDATE CASCADE,
	owner integer NOT NULL REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE
);

CREATE TABLE domain_mail_settings (
	domain_id integer PRIMARY KEY REFERENCES domains(id) ON DELETE CASCADE ON UPDATE CASCADE,
	mailboxes integer NOT NULL DEFAULT '0',
	quota integer NOT NULL DEFAULT '0'
);

CREATE TABLE domain_web_settings (
	domain_id integer PRIMARY KEY REFERENCES domains(id) ON DELETE CASCADE ON UPDATE CASCADE,
	quota integer NOT NULL DEFAULT '0'
);

CREATE TABLE domain_users (
	id serial PRIMARY KEY,
	user_id integer NOT NULL REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
	domain_id integer NOT NULL REFERENCES domains(id) ON DELETE CASCADE ON UPDATE CASCADE,
	username varchar(32) NOT NULL,
	UNIQUE (username, domain_id)
);

CREATE TABLE domain_user_settings (
	domain_user_id integer PRIMARY KEY REFERENCES domain_users(id) ON DELETE CASCADE ON UPDATE CASCADE,
	spam_level float DEFAULT NULL
);

CREATE TABLE domain_user_aliases (
	domain_user_id integer NOT NULL REFERENCES domain_users(id) ON DELETE CASCADE ON UPDATE CASCADE,
	alias varchar(384) NOT NULL,
	PRIMARY KEY (domain_user_id, alias)
);

CREATE TABLE web_engines (
	id serial PRIMARY KEY NOT NULL,
	name varchar(16) NOT NULL UNIQUE,
	cgi_path varchar(512) NOT NULL
);

CREATE TABLE web_configurations (
	id serial PRIMARY KEY NOT NULL,
	web_engine integer NOT NULL REFERENCES web_engines(id) ON DELETE CASCADE ON UPDATE CASCADE,
	name varchar(128) NOT NULL UNIQUE,
	description text NOT NULL,
	template varchar(512) NOT NULL	
);

CREATE TABLE user_web_configurations (
	user_id integer NOT NULL REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
	web_configuration_id integer NOT NULL REFERENCES web_configurations(id) ON DELETE CASCADE ON UPDATE CASCADE,
	PRIMARY KEY (user_id, web_configuration_id)
);

CREATE TABLE domain_web_configurations (
	domain_id integer NOT NULL REFERENCES domains(id) ON DELETE CASCADE ON UPDATE CASCADE,
	web_configuration_id integer NOT NULL REFERENCES web_configurations(id) ON DELETE CASCADE ON UPDATE CASCADE,
	PRIMARY KEY (domain_id, web_configuration_id)
);

GRANT SELECT ON users TO inkspot_ro;
GRANT SELECT ON groups TO inkspot_ro;
GRANT SELECT ON user_groups TO inkspot_ro;

INSERT INTO web_engines (name, cgi_path) VALUES('PHP5', '/usr/bin/php5-cgi');
INSERT INTO web_engines (name, cgi_path) VALUES('Ruby', '/usr/bin/ruby-cgi');

INSERT INTO web_configurations (name, web_engine, description, template) VALUES(
	'PHP5',
	(SELECT name FROM web_engines WHERE name = 'PHP5'),
	'Standard PHP support for files ending with .php',
	'/etc/inkspot/nginx/php.tmpl'
);

INSERT INTO web_configurations (name, web_engine, description, template) VALUES(
	'Ruby',
	(SELECT name FROM web_engines WHERE name = 'Ruby'),
	'Standard Ruby support for files ending with .rb',
	'/etc/inkspot/nginx/ruby.tmpl'
);

COMMIT;
