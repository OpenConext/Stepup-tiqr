Feature: When an user needs to authenticate
  As a service provider
  I need to send an AuthnRequest with a nameID to the identity provider

  Background:
    Given the registration QR code is scanned
    And the user registers the service with notification type "APNS" address: "0000000000111111111122222222223333333333"
    Then we have a registered user
    And I clear the logs

#    @TODO tests, are red, implement and make green

#  Scenario: When a user authenticates using a qr code it should set a trusted cookie
#    Given I am on "/demo/sp"
#    And I fill in "NameID" with my identifier
#    When I press "authenticate"
#
#    Then I should see "Log in with tiqr"
#    And I should be on "/authentication"
#
#    Then I scan the tiqr authentication qrcode
#    And the app authenticates to the service
#    Then we have a authenticated app
#    And we have a trusted cookie
#
#  Scenario: When a authenticates wihout a trusted cookie
#    # Service provider demo page
#    Given I am on "/demo/sp"
#    And I fill in "NameID" with my identifier
#    When I press "authenticate"
#    And I should be on "/authentication"
#    When a push notification is sent
#    Then it should fail with "no-trusted-cookie"

#  @TODO this will become red one above features are implemented, make green again (using the cookie service directly?)
  Scenario: When a user authenticates with a trusted cookie
    Given I am on "/demo/sp"
    And I fill in "NameID" with my identifier
    When I press "authenticate"
    And I should be on "/authentication"
    When a push notification is sent
    Then it should send a notification for the user with type "APNS" and address "0000000000111111111122222222223333333333"


#  @TODO Add a test somewhere, maybe not here, that tests the cookie get overwritten properly (or appended) if a new scan occurs?
  Scenario: Handles multiple devices / userids
#    Given the user with ID X scans qr code
#    Then A cookie is set for the stored device id
#    When the user with ID Y scans a qr code in the same browser
#    Then A cookie is appended or new cookie is created for the new device id
