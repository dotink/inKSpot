BEGIN;

CREATE FUNCTION epoch_seconds() RETURNS integer
	AS 'SELECT extract(EPOCH FROM CURRENT_TIMESTAMP)::int;'
	LANGUAGE SQL;

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
	name varchar(48) DEFAULT '',
	location varchar(64) DEFAULT '',
	phone_number varchar(16) DEFAULT '',
	group_id int4 REFERENCES groups(id) ON DELETE RESTRICT ON UPDATE CASCADE,
	home varchar(512) NOT NULL,
	shell varchar(512) NOT NULL DEFAULT '/usr/bin/rssh',
	avatar varchar(512) DEFAULT NULL
);

CREATE TABLE user_groups (
	group_id int4 NOT NULL REFERENCES groups(id) ON DELETE CASCADE ON UPDATE CASCADE,
	user_id int4 NOT NULL REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
	PRIMARY KEY (group_id, user_id)
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
	type varchar(6) NOT NULL CHECK(type IN('MASTER', 'SLAVE', 'NATIVE', 'internal')) DEFAULT 'MASTER',
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
	change_date int DEFAULT epoch_seconds()
);

CREATE INDEX rec_name_index ON domain_records(name);
CREATE INDEX nametype_index ON domain_records(name,type);
CREATE INDEX domain_id ON domain_records(domain_id);

CREATE TABLE domain_supermasters (
	ip varchar(45) NOT NULL,
	nameserver varchar(256) NOT NULL,
	account varchar(40) DEFAULT NULL
);

CREATE TABLE domain_users (
	user_id integer NOT NULL REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
	domain_id integer NOT NULL REFERENCES domains(id) ON DELETE CASCADE ON UPDATE CASCADE,
	PRIMARY KEY (user_id, domain_id)
);

INSERT INTO groups (id, groupname, description) VALUES (
	9999, '_everyone_', 'The _everyone_ group contains all users.'
);

COMMIT;
