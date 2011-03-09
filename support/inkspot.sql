CREATE USER inkspot;
CREATE DATABASE inkspot OWNER inkspot;

\c inkspot

CREATE TABLE domains (
	id SERIAL PRIMARY KEY,
	name varchar(256) NOT NULL
);

CREATE TABLE domain_options (
	option varchar(16) PRIMARY_KEY CHECK(option IN(
		'enable_website',
		'enable_htccess',
		'enable_asp',
		'enable_sending_email',
		'enable_receiving_email',
	)),
	value boolean DEFAULT FALSE
);

CREATE TABLE domain_users (
	id SERIAL PRIMARY KEY,
	user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
	domain_id INTEGER NOT NULL REFERENCES domains(id) ON DELETE CASCADE ON UPDATE CASCADE,
	alias varchar(32) NOT NULL,
	password varchar(512) NOT NULL,
);

CREATE TABLE users (
	id SERIAL PRIMARY KEY,
	username varchar(32) NOT NULL UNIQUE,
	password varchar(512) NOT NULL
);
