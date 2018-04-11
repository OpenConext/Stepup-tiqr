Feature: User
  To authenticates with service
  As a user from the tiqr app
  I need to to scan the authentication url

  Background:
    Given the registration QR code is scanned
    When the user registers the service
    Then we have a registered user

  Scenario: Register a new service
    Given the authentication QR code is scanned
    When the app authenticates to the service
    Then we have a authenticated user

  Scenario: The authentication QR code can only be used a single time
    Given the authentication QR code is scanned
    When the app authenticates to the service
    Then we have a authenticated user
    And the app authenticates to the service
    Then we have the authentication error 'INVALID_CHALLENGE'

  Scenario: The authentication fails with wrong password
    Given the authentication QR code is scanned
    When the app authenticates to the service with wrong password
    Then we have the authentication error 'INVALID_RESPONSE:4'

  Scenario: The user attempts is reset after he successfully authenticated
    And the authentication QR code is scanned
    When the app authenticates to the service with wrong password
    Then we have the authentication error 'INVALID_RESPONSE:4'
    And the app authenticates to the service with wrong password
    Then we have the authentication error 'INVALID_RESPONSE:3'

    And the app authenticates to the service
    Then we have a authenticated user

    And the authentication QR code is scanned
    And the app authenticates to the service with wrong password
    Then we have the authentication error 'INVALID_RESPONSE:4'

  Scenario: The user is blocked after to many attempts
    And the authentication QR code is scanned
    When the app authenticates to the service with wrong password
    Then we have the authentication error 'INVALID_RESPONSE:4'
    And the app authenticates to the service with wrong password
    Then we have the authentication error 'INVALID_RESPONSE:3'
    And the app authenticates to the service with wrong password
    Then we have the authentication error 'INVALID_RESPONSE:2'
    And the app authenticates to the service with wrong password
    Then we have the authentication error 'INVALID_RESPONSE:1'
    # Last attempt
    And the app authenticates to the service with wrong password
    Then we have the authentication error 'ACCOUNT_BLOCKED'
    
    # One more time with wrong password
    And the app authenticates to the service with wrong password
    Then we have the authentication error 'ACCOUNT_BLOCKED'

    # Try it with the actual correct password
    And the app authenticates to the service
    Then we have the authentication error 'ACCOUNT_BLOCKED'
