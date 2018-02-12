@skip
@remote
Feature: When an user needs to register for a new token
  To register an user for a new token
  As a service provider
  I need to send an AuthnRequest to the identity provider

  Scenario: When an user needs to register for a new token
    # The user request a registration from the service provider
    Given I am on "https://tiqr.example.com/app_dev.php/demo/sp"
    Then I should see "Demo service provider"
    When I press "Register user"

    # The user register himself at the IdP
    Then I should see "Registration"
    And I should be on "https://tiqr.example.com/app_dev.php/registration"

    # GSSP assigns a subject name id to the user
    Given I fill in "Subject NameID" with "test-name-id-1234"
    When I press "Register user"

    # The SSO return page
    Then I should be on "https://tiqr.example.com/app_dev.php/saml/sso_return"
    Given I press "Submit"

    # Back at the SP.
    And I should see "Demo Service provider ConsumerAssertionService endpoint"
    And I should see "test-name-id-1234"
