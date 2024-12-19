Feature: When an user needs to authenticate
  As a service provider
  I need to send an AuthnRequest with a nameID to the identity provider

  Background:
    Given the registration QR code is scanned
    And the user registers the service with notification type "APNS" address: "0000000000111111111122222222223333333333"
    Then we have a registered user
    And the logs should mention: Writing a trusted-device cookie with fingerprint
    And I clear the logs
    And the trusted device cookie is cleared

  Scenario: When a user authenticates using a qr code it should set a trusted cookie
    Given I am on "/demo/sp"
    And I fill in "NameID" with my identifier
    When I press "authenticate"
    Then I should see "Log in with tiqr"
    And I should be on "/authentication"

    Then I scan the tiqr authentication qrcode
    And the app authenticates to the service with notification type "APNS" address: "0000000000111111111122222222223333333333"
    Then we have a authenticated app
    And we have a trusted cookie for address: "0000000000111111111122222222223333333333"

  Scenario: When a user authenticates without a trusted cookie, a push notification should not be sent
    Given I am on "/demo/sp"
    And I fill in "NameID" with my identifier
    When I press "authenticate"
    Then I should see "Log in with tiqr"
    And I should be on "/authentication"

    When a push notification is sent
    Then it should fail with "no-trusted-device"
    Then the logs should say: no trusted cookie for address "0000000000111111111122222222223333333333"

  Scenario: When a user tries to authenticates with a trusted cookie, a notification should be sent
    Given I am on "/demo/sp"
    And I fill in "NameID" with my identifier
    When I press "authenticate"
    Then I should see "Log in with tiqr"
    And I should be on "/authentication"

    When push notification is sent with a trusted-device cookie with address "0000000000111111111122222222223333333333"
    Then it should send a notification for the user with type "APNS" and address "0000000000111111111122222222223333333333"

  Scenario: When a user tries to authenticates with a trusted cookie, but changes the address, a notification should not be sent
    Given I am on "/demo/sp"
    And I fill in "NameID" with my identifier
    When I press "authenticate"
    Then I should see "Log in with tiqr"
    And I should be on "/authentication"

    When push notification is sent with a trusted-device cookie with address "0000000000111111111122222222223333333333" and cookie userId "abc-1234"
    Then the logs should mention a signature mismatch for address "0000000000111111111122222222223333333333"
    And it should fail with "no-trusted-device"
