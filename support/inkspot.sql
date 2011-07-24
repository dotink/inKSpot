BEGIN;

CREATE SEQUENCE group_id MINVALUE 10000 MAXVALUE 2147483647 NO CYCLE;
CREATE SEQUENCE user_id MINVALUE 10000 MAXVALUE 2147483647 NO CYCLE;

CREATE TABLE activation_requests (
	key varchar(256) NOT NULL PRIMARY KEY,
	name varchar(64) NOT NULL,
	email_address varchar(256) NOT NULL UNIQUE
);

CREATE TABLE groups (
	id int4 NOT NULL PRIMARY KEY DEFAULT nextval('group_id'),
	groupname varchar(64) NOT NULL,
	description text DEFAULT NULL,
	login_password varchar(512) DEFAULT 'x'
);

CREATE TABLE users (
	id int4 NOT NULL PRIMARY KEY DEFAULT nextval('user_id'),
	username varchar(32) NOT NULL UNIQUE,
	name varchar(48) DEFAULT '',
	location varchar(64) DEFAULT '',
	phone_number varchar(16) DEFAULT '',
	group_id int4 NOT NULL REFERENCES groups(id) ON DELETE RESTRICT ON UPDATE CASCADE,
	home varchar(512) NOT NULL,
	shell varchar(512) NOT NULL DEFAULT '/usr/bin/rssh',
	avatar varchar(512) DEFAULT NULL
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

CREATE TABLE domains (
	id serial PRIMARY KEY,
	name varchar(256) NOT NULL UNIQUE,
	group_id int4 NOT NULL REFERENCES groups(id) ON DELETE RESTRICT ON UPDATE CASCADE,
	user_id int4 NOT NULL REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,
	description varchar(256) NOT NULL,
	master varchar(128) DEFAULT NULL,
	last_check int DEFAULT NULL,
	type varchar(6) NOT NULL CHECK(type IN('MASTER', 'SLAVE', 'internal'))) DEFAULT 'MASTER',
	notified_serial INT DEFAULT NULL,
	account VARCHAR(40) DEFAULT NULL		
);

CREATE TABLE domain_records (
	id SERIAL PRIMARY KEY,
	domain_id integer NOT NULL REFERENCES domains(id) ON DELETE CASCADE ON UPDATE CASCADE,
	name varchar(256) DEFAULT NULL,
	type varchar(6) NOT NULL CHECK(type IN('SOA', 'NS', 'MX', 'A', 'AAAA', 'CNAME')) DEFAULT 'A',
	content VARCHAR(256) DEFAULT NULL,
	ttl int4 DEFAULT '1200',
	prio int4 DEFAULT NULL,
	change_date int DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE domain_aliases

CREATE INDEX rec_name_index ON records(name);
CREATE INDEX nametype_index ON records(name,type);
CREATE INDEX domain_id ON records(domain_id);

CREATE TABLE domain_supermasters (
	ip varchar(45) NOT NULL,
	nameserver varchar(256) NOT NULL,
	account varchar(40) DEFAULT NULL
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

CREATE TABLE user_web_engines (
	web_engine_id integer NOT NULL  REFERENCES web_engines(id) ON DELETE CASCADE ON UPDATE CASCADE,
	user_id integer NOT NULL REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
	pid integer DEFAULT NULL,
	PRIMARY KEY (web_engine_id, user_id)
);

CREATE TABLE domain_web_engines (
	web_engine_id integer NOT NULL  REFERENCES web_engines(id) ON DELETE CASCADE ON UPDATE CASCADE,
	domain_id integer NOT NULL REFERENCES domains(id) ON DELETE CASCADE ON UPDATE CASCADE,
	pid integer DEFAULT NULL,
	PRIMARY KEY (web_engine_id, domain_id)
);

CREATE TABLE web_configurations (
	id serial PRIMARY KEY NOT NULL,
	web_engine_id integer NOT NULL REFERENCES web_engines(id) ON DELETE CASCADE ON UPDATE CASCADE,
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

INSERT INTO web_engines (name, cgi_path) VALUES('php5', '/usr/bin/php5-cgi');
INSERT INTO web_engines (name, cgi_path) VALUES('ruby', '/usr/bin/ruby-cgi');

INSERT INTO web_configurations (name, web_engine_id, description, template) VALUES(
	'PHP5',
	(SELECT id FROM web_engines WHERE name = 'php5'),
	'Standard PHP support for files ending with .php',
	'/etc/inkspot/nginx/php.tmpl'
);

INSERT INTO web_configurations (name, web_engine_id, description, template) VALUES(
	'Ruby',
	(SELECT id FROM web_engines WHERE name = 'ruby'),
	'Standard Ruby support for files ending with .rb',
	'/etc/inkspot/nginx/ruby.tmpl'
);

COMMIT;
