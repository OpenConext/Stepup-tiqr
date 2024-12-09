/*
 Initially, the notificationtype column was created as VARCHAR(10). However,
 this size is too small to accommodate the value 'APNS_DIRECT'.
*/
ALTER TABLE user MODIFY notificationtype VARCHAR (15);

