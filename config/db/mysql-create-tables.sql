CREATE TABLE IF NOT EXISTS user (
  id integer NOT NULL PRIMARY KEY AUTO_INCREMENT,
  userid varchar(30) NOT NULL UNIQUE,
  displayname varchar(30) NOT NULL,
  secret varchar(128),
  loginattempts integer,
  tmpblocktimestamp BIGINT,
  tmpblockattempts integer,
  blocked tinyint(1),
  notificationtype varchar(15),
  notificationaddress varchar(256)
);

CREATE TABLE IF NOT EXISTS tiqrstate (
  `key` varchar(255) PRIMARY KEY,
  expire BIGINT,
  `value` text
);

CREATE INDEX IF NOT EXISTS index_tiqrstate_expire ON tiqrstate (expire);
