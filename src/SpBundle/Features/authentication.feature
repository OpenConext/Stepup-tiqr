@skip
@remote
Feature: When an user needs to authenticate
  As a service provider
  I need to send an AuthnRequest with a nameID to the identity provider

  Scenario: When an user needs to register for a new token

    # The user clicks on authenticate button from the SP
    Given I am on "https://tiqr.example.com/app_dev.php/demo/sp"
    Then I should see "Demo service provider"
    And I fill in "Subject NameID" with "test-name-id-1234"
    Given I press "Authenticate user"

    # The user clicks on authenticate button from the GSSP IdP
    Then I should be on "https://tiqr.example.com/app_dev.php/authentication"
    Given I press "Authenticate user"

    # The SSO return page
    Then I should be on "https://tiqr.example.com/app_dev.php/saml/sso_return"
    Given I press "Submit"

    # Returns to the SP
    And I should see "Demo Service provider ConsumerAssertionService endpoint"
    And I should see "test-name-id-1234"
