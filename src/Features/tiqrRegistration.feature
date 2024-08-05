Feature: User
  To register an new service
  As a user from the tiqr app
  I need to to scan the registration url

  Scenario: Register a new service
    Given the registration QR code is scanned
    When the user registers the service with notification type "APNS" address: "0000000000111111111122222222223333333333"
    Then we have a registered user

  Scenario: The registration QR is only valid once
    Given the registration QR code is scanned
    When the user registers the service with notification type "APNS" address: "0000000000111111111122222222223333333333"
    Then we register with the same QR code it should not work anymore.

  Scenario: Register a new service with unknown user agent
    Given the registration QR code is scanned
    And the mobile tiqr app identifies itself with the user agent "Bad UA"
    When the user registers the service
    Then tiqr errors with a message telling the user agent was wrong

  Scenario: Registration without notification type and address is allowed
    Given the registration QR code is scanned
    When the user registers the service with notification type "NULL" address: "NULL"
    Then we have a registered user
