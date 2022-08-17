CREATE TABLE IF NOT EXISTS user (
  id integer NOT NULL PRIMARY KEY AUTO_INCREMENT,
  userid varchar(30) NOT NULL UNIQUE,
  displayname varchar(30) NOT NULL,
  secret varchar(128),
  loginattempts integer,
  tmpblocktimestamp BIGINT,
  tmpblockattempts BIGINT,
  blocked tinyint(1),
  notificationtype varchar(10),
  notificationaddress varchar(64)
);

CREATE TABLE IF NOT EXISTS tiqrstate (
  key varchar(255) PRIMARY KEY,
  expire BIGINT,
  value text
);
