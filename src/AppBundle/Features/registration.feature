@skip
Feature: When an user needs to register for a new token
  To register an user for a new token
  As a service provider
  I need to send an AuthnRequest to the identity provider

  @remote
  Scenario: When an user needs to register for a new token
    Given I am on "https://pieter.aai.surfnet.nl/simplesamlphp/sp.php?sp=default-sp"
    And I select "https://tiqr.example.com/app_dev.php/saml/metadata" from "idp"
    When I press "Login"
    Then I should see "Registration"
    And I should be on "https://tiqr.example.com/app_dev.php/registration"

    Given I fill in "Subject NameID" with "test-name-id-1234"
    When I press "Register user"
    Then I press "Submit"
    And I should see "You are logged in to SP:default-sp"
    And I should see "IdP EnitytID:https://tiqr.example.com/app_dev.php/saml/metadata"
    And I should see "test-name-id-1234"

  Scenario: When the user is redirected from an unknown service provider he should see an error page
    Given a normal SAML 2.0 AuthnRequest form a unknown service provider
    Then the response status code should be 406
    And I should see "AuthnRequest received from ServiceProvider with an unknown EntityId: \"https://service_provider_unkown/saml/metadata\""

  Scenario: When an user request the sso endpoint without AuthnRequest the request should be denied
    When I am on "/saml/sso"
    Then the response status code should be 406
    And I should see "Could not receive AuthnRequest from HTTP Request: expected query parameters, none found"
