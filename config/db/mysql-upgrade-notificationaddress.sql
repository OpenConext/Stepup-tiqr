/*
 Initially the notificationaddress column was created as varchar(64). That is fine for
 tokenaxchange addresses, but too small for FCM addresses.
 */
ALTER TABLE user MODIFY notificationaddress VARCHAR (256);
