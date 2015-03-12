CREATE TABLE "user" (
    "id" integer NOT NULL PRIMARY KEY,
    "userid" varchar(30) NOT NULL UNIQUE,
    "displayname" varchar(30) NOT NULL,
    "secret" varchar(128),
    "loginattempts" integer,
    "tmpblocktimestamp" datetime,
    "tmpblockattempts" datetime,
    "blocked" bool,
    "notificationtype" varchar(10),
    "notificationaddress" varchar(64)
);
