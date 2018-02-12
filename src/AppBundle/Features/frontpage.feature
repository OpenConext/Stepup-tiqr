Feature: Web definitions
  In order to know if the frond-page is working
  As a developer
  I need to see the welcome message

  Scenario: See welcome message
    When I go to "/"
    Then I should see "Welcome to"
