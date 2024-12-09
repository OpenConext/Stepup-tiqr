/*
 Initially the notificationtype column was created as varchar(10). That is 
 but too small for 'APNS_DIRECT'.
 */
ALTER TABLE user MODIFY notificationtype VARCHAR (15);

