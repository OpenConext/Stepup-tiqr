Feature: User
  To register an new service
  As a user from the tiqr app
  I need to to scan the registration url

  Scenario: Register a new service
    Given the qr code is scanned
    When the user registers the service with notification type "APNS" address: "0000000000111111111122222222223333333333"
    Then we have a registered user

  Scenario: The registration QR is only valid once
    Given the qr code is scanned
    When the user registers the service with notification type "APNS" address: "0000000000111111111122222222223333333333"
    Then we register with the same qr code it should not work anymore.
