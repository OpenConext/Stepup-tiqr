# Peronal Data in Stepup-tiqr

See https://github.com/OpenConext/Stepup-Deploy/wiki/Personal-Data for an overview of all personal data in Stepup.

Data that we think should be considered "personal data" is *emphasised* in the table below.

The `tiqr` database tracks the registered tokens. When the keyserver is used the `secret` is stored in the keyserver, and not in the tiqr database.

| Data                  | Description                                                                                                           |
|:----------------------|:----------------------------------------------------------------------------------------------------------------------|
| *userid*              | the ID of the tiqr token, this corresponds to `second_factor_id` in thr Stepup-Middleware                             |
| displayname           | unused, always `anonymous`                                                                                            |
| *secret*              | the OCRA secret key associated with the Tiqr account. When the keyserver is used, this key as stored on the keyserver |
| loginattempts         | Used to track (failed) login atempts                                                                                  |
| tmpblocktimestamp     | Used to track (failed) login atempts                                                                                  |
| tmpblockattempts      | Used to track (failed) login atempts                                                                                  |
| blocked               | Flag indicating whether the account is blocked                                                                        |
| notificationtype      | `APNS` or `GCM`                                                                                                       |
| *notificationaddress* | This is the ID of the mobile phone for the Tiqr application at Apple or Google                                        |
