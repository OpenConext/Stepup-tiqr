Feature: When an user needs to authenticate
  As a service provider
  I need to send an AuthnRequest with a nameID to the identity provider

  Background:
    Given the registration QR code is scanned
    When the user registers the service
    Then we have a registered user
    And I clear the logs

  Scenario: When an user needs to authenticate
    # Service provider demo page
    Given I am on "/demo/sp"
    And I fill in "NameID" with my identifier
    When I press "authenticate"

    # Tiqr authentication page with qr code
    Then I should see "Log in with tiqr"
    And I should be on "/authentication"

    # The app
    Then I scan the tiqr authentication qrcode
    And the app authenticates to the service
    Then we have a authenticated app

    # Refresh tiqr page to see get redirection
    And I am on "/authentication"

    # GSSP sso-return endpoint to return to the service provider
    And I should see "Javascript is disabled, please click the button below to proceed"
    And I press "Submit"

    # Service provider AuthNRequest response page
    Then I should see "urn:oasis:names:tc:SAML:2.0:status:Success"

    And the logs are:
      | level  | message                                                                                                                                   | sari    |
      | info   | User made a request with a session cookie.                                                                                                |         |
      | info   | Created new session.                                                                                                                      |         |
      | info   | User has a session.                                                                                                                       |         |
      | info   | User session matches the session cookie.                                                                                                  |         |
      | info   | User made a request with a session cookie.                                                                                                |         |
      | info   | Created new session.                                                                                                                      |         |
      | info   | User has a session.                                                                                                                       |         |
      | info   | User session matches the session cookie.                                                                                                  |         |
      | info   | User made a request with a session cookie.                                                                                                |         |
      | info   | Created new session.                                                                                                                      |         |
      | info   | User has a session.                                                                                                                       |         |
      | info   | User session matches the session cookie.                                                                                                  |         |
      | notice | Received sso request                                                                                                                      |         |
      | info   | Processing AuthnRequest                                                                                                                   |         |
      | notice | /AuthnRequest processing complete, received AuthnRequest from "https:\/\/tiqr\.dev\.openconext\.local\/saml\/metadata", request ID: ".*"/ |         |
      | info   | AuthnRequest stored in state                                                                                                              |         |
      | notice | Redirect user to the application authentication route /authentication                                                                     |         |
      | info   | User made a request with a session cookie.                                                                                                | present |
      | info   | Created new session.                                                                                                                      |         |
      | info   | User has a session.                                                                                                                       | present |
      | info   | User session matches the session cookie.                                                                                                  | present |
      | info   | Using "plain" as UserSecretStorage encryption type                                                                                        | present |
      | info   | Using "plain" as UserSecretStorage encryption type                                                                                        | present |
      | info   | Verifying if there is a pending authentication request from SP                                                                            | present |
      | info   | Verify if user is blocked                                                                                                                 | present |
      | info   | Verifying if authentication is finalized                                                                                                  | present |
      | notice | Unable to retrieve the state storage value, file not found                                                                                | present |
      | info   | Start authentication                                                                                                                      | present |
      | info   | /Setting SARI '.*' for identifier '.*'/                                                                                                   | present |
      | info   | Return authentication page with QR code                                                                                                   | present |
      | info   | User made a request with a session cookie.                                                                                                | present |
      | info   | Created new session.                                                                                                                      |         |
      | info   | User has a session.                                                                                                                       | present |
      | info   | User session matches the session cookie.                                                                                                  | present |
      | info   | Client request QR image                                                                                                                   | present |
      | info   | Return QR image response                                                                                                                  | present |
      | info   | User made a request without a session cookie.                                                                                             | present |
      | notice | Got POST with login response                                                                                                              | present |
      | notice | Received login action from client with User-Agent "Symfony" and version ""                                                                | present |
      | info   | Validating authentication response                                                                                                        | present |
      | notice | /Authenticated user ".*" in session ".*"/                                                                                                 | present |
      | info   | response is valid                                                                                                                         | present |
      | notice | User authenticated OK                                                                                                                     | present |
      | info   | Created new session.                                                                                                                      |         |
      | info   | User made a request with a session cookie.                                                                                                | present |
      | info   | Created new session.                                                                                                                      |         |
      | info   | User has a session.                                                                                                                       | present |
      | info   | User session matches the session cookie.                                                                                                  | present |
      | info   | Using "plain" as UserSecretStorage encryption type                                                                                        | present |
      | info   | Using "plain" as UserSecretStorage encryption type                                                                                        | present |
      | info   | Verifying if there is a pending authentication request from SP                                                                            | present |
      | info   | Verify if user is blocked                                                                                                                 | present |
      | info   | Verifying if authentication is finalized                                                                                                  | present |
      | info   | Authentication is finalized, returning to SP                                                                                              | present |
      | notice | Application authenticates the user                                                                                                        | present |
      | notice | Created redirect response for sso return endpoint "/saml/sso_return"                                                                      | present |
      | info   | User made a request with a session cookie.                                                                                                | present |
      | info   | Created new session.                                                                                                                      |         |
      | info   | User has a session.                                                                                                                       | present |
      | info   | User session matches the session cookie.                                                                                                  | present |
      | notice | Received sso return request                                                                                                               |         |
      | info   | Create sso response                                                                                                                       |         |
      | notice | /Saml response created with id ".*", request ID: ".*"/                                                                                    |         |
      | notice | Invalidate current state and redirect user to service provider assertion consumer url "https://tiqr.dev.openconext.local/demo/sp/acs"     |         |
      | info   | User made a request with a session cookie.                                                                                                |         |
      | info   | Created new session.                                                                                                                      |         |
      | info   | User has a session.                                                                                                                       |         |
      | info   | User session matches the session cookie.                                                                                                  |         |
      | info   | /SAMLResponse with id ".*" was not signed at root level, not attempting to verify the signature of the reponse itself/                    |         |
      | info   | /Verifying signature of Assertion with id ".*"/                                                                                           |         |


  Scenario: When an user cancels it's authentication
    # Service provider demo page
    Given I am on "/demo/sp"
    And I fill in "Subject name id" with my identifier
    When I press "Authenticate user"

    # Tiqr authentication page with QR code
    Then I should see "Log in with tiqr"
    And I should be on "/authentication"

    # The app
    Then I follow "Cancel"

    # GSSP sso-return endpoint to return to the service provider
    And I should see "Javascript is disabled, please click the button below to proceed"
    And I press "Submit"

    # Service provider AuthNRequest response page
    Then I should see "Cannot process response, preconditions not met: \"Responder/AuthnFailed User cancelled the request\""

    And the logs are:
      | level    | message                                                                                                                                   | sari    |
      | info     | User made a request with a session cookie.                                                                                                |         |
      | info     | Created new session.                                                                                                                      |         |
      | info     | User has a session.                                                                                                                       |         |
      | info     | User session matches the session cookie.                                                                                                  |         |
      | info     | User made a request with a session cookie.                                                                                                |         |
      | info     | Created new session.                                                                                                                      |         |
      | info     | User has a session.                                                                                                                       |         |
      | info     | User session matches the session cookie.                                                                                                  |         |
      | info     | User made a request with a session cookie.                                                                                                |         |
      | info     | Created new session.                                                                                                                      |         |
      | info     | User has a session.                                                                                                                       |         |
      | info     | User session matches the session cookie.                                                                                                  |         |
      | notice   | Received sso request                                                                                                                      |         |
      | info     | Processing AuthnRequest                                                                                                                   |         |
      | notice   | /AuthnRequest processing complete, received AuthnRequest from "https:\/\/tiqr\.dev\.openconext\.local\/saml\/metadata", request ID: ".*"/ |         |
      | info     | AuthnRequest stored in state                                                                                                              |         |
      | notice   | Redirect user to the application authentication route /authentication                                                                     |         |
      | info     | User made a request with a session cookie.                                                                                                | present |
      | info     | Created new session.                                                                                                                      |         |
      | info     | User has a session.                                                                                                                       | present |
      | info     | User session matches the session cookie.                                                                                                  | present |
      | info     | Using "plain" as UserSecretStorage encryption type                                                                                        | present |
      | info     | Using "plain" as UserSecretStorage encryption type                                                                                        | present |
      | info     | Verifying if there is a pending authentication request from SP                                                                            | present |
      | info     | Verify if user is blocked                                                                                                                 | present |
      | info     | Verifying if authentication is finalized                                                                                                  | present |
      | notice   | Unable to retrieve the state storage value, file not found                                                                                | present |
      | info     | Start authentication                                                                                                                      | present |
      | info     | /Setting SARI '.*' for identifier '.*'/                                                                                                   | present |
      | info     | Return authentication page with QR code                                                                                                   | present |
      | info     | User made a request with a session cookie.                                                                                                | present |
      | info     | Created new session.                                                                                                                      |         |
      | info     | User has a session.                                                                                                                       | present |
      | info     | User session matches the session cookie.                                                                                                  | present |
      | notice   | User cancelled the request                                                                                                                | present |
      | critical | User cancelled the request                                                                                                                | present |
      | info     | Redirect to sso return endpoint with authentication reject response                                                                       | present |
      | notice   | Created redirect response for sso return endpoint "/saml/sso_return"                                                                      | present |
      | info     | User made a request with a session cookie.                                                                                                | present |
      | info     | Created new session.                                                                                                                      |         |
      | info     | User has a session.                                                                                                                       | present |
      | info     | User session matches the session cookie.                                                                                                  | present |
      | notice   | Received sso return request                                                                                                               |         |
      | info     | Create sso response                                                                                                                       |         |
      | notice   | /Saml response created with id ".*", request ID: ".*"/                                                                                    |         |
      | notice   | Invalidate current state and redirect user to service provider assertion consumer url "https://tiqr.dev.openconext.local/demo/sp/acs"     |         |
      | info     | User made a request with a session cookie.                                                                                                |         |
      | info     | Created new session.                                                                                                                      |         |
      | info     | User has a session.                                                                                                                       |         |
      | info     | User session matches the session cookie.                                                                                                  |         |

  Scenario: An user can authenticate with a one time password
    # Service provider demo page
    Given I am on "/demo/sp"
    And I fill in "Subject name id" with my identifier
    When I press "Authenticate user"

    # Tiqr authentication page with qr code
    Then I should see "Log in with tiqr"
    And I should be on "/authentication"

    # The app
    Then I scan the tiqr authentication qrcode
    And I fill in "One time password" with my one time password and press ok

    # GSSP sso-return endpoint to return to the service provider
    And I should see "Javascript is disabled, please click the button below to proceed"
    And I press "Submit"

    # Service provider AuthNRequest response page
    Then I should see "urn:oasis:names:tc:SAML:2.0:status:Success"

    And the logs are:
      | level  | message                                                                                                                                   | sari    |

      | info   | User made a request with a session cookie.                                                                                                |         |
      | info   | Created new session.                                                                                                                      |         |
      | info   | User has a session.                                                                                                                       |         |
      | info   | User session matches the session cookie.                                                                                                  |         |
      | info   | User made a request with a session cookie.                                                                                                |         |
      | info   | Created new session.                                                                                                                      |         |
      | info   | User has a session.                                                                                                                       |         |
      | info   | User session matches the session cookie.                                                                                                  |         |
      | info   | User made a request with a session cookie.                                                                                                |         |
      | info   | Created new session.                                                                                                                      |         |
      | info   | User has a session.                                                                                                                       |         |
      | info   | User session matches the session cookie.                                                                                                  |         |
      | notice | Received sso request                                                                                                                      |         |
      | info   | Processing AuthnRequest                                                                                                                   |         |
      | notice | /AuthnRequest processing complete, received AuthnRequest from "https:\/\/tiqr\.dev\.openconext\.local\/saml\/metadata", request ID: ".*"/ |         |
      | info   | AuthnRequest stored in state                                                                                                              |         |
      | notice | Redirect user to the application authentication route /authentication                                                                     |         |
      | info   | User made a request with a session cookie.                                                                                                | present |
      | info   | Created new session.                                                                                                                      |         |
      | info   | User has a session.                                                                                                                       | present |
      | info   | User session matches the session cookie.                                                                                                  | present |
      | info   | Using "plain" as UserSecretStorage encryption type                                                                                        | present |
      | info   | Using "plain" as UserSecretStorage encryption type                                                                                        | present |
      | info   | Verifying if there is a pending authentication request from SP                                                                            | present |
      | info   | Verify if user is blocked                                                                                                                 | present |
      | info   | Verifying if authentication is finalized                                                                                                  | present |
      | notice | Unable to retrieve the state storage value, file not found                                                                                | present |
      | info   | Start authentication                                                                                                                      | present |
      | info   | /Setting SARI '.*' for identifier '.*'/                                                                                                   | present |
      | info   | Return authentication page with QR code                                                                                                   | present |
      | info   | User made a request with a session cookie.                                                                                                | present |
      | info   | Created new session.                                                                                                                      |         |
      | info   | User has a session.                                                                                                                       | present |
      | info   | User session matches the session cookie.                                                                                                  | present |
      | info   | Client request QR image                                                                                                                   | present |
      | info   | Return QR image response                                                                                                                  | present |
      | info   | User made a request with a session cookie.                                                                                                | present |
      | info   | Created new session.                                                                                                                      |         |
      | info   | User has a session.                                                                                                                       | present |
      | info   | User session matches the session cookie.                                                                                                  | present |
      | info   | Using "plain" as UserSecretStorage encryption type                                                                                        | present |
      | info   | Using "plain" as UserSecretStorage encryption type                                                                                        | present |
      | info   | Verifying if there is a pending authentication request from SP                                                                            | present |
      | info   | Verify if user is blocked                                                                                                                 | present |
      | info   | Handling otp                                                                                                                              | present |
      | info   | Validating authentication response                                                                                                        | present |
      | notice | /Authenticated user ".*" in session ".*"/                                                                                                 | present |
      | info   | response is valid                                                                                                                         | present |
      | info   | Verifying if authentication is finalized                                                                                                  | present |
      | info   | Authentication is finalized, returning to SP                                                                                              | present |
      | notice | Application authenticates the user                                                                                                        | present |
      | notice | Created redirect response for sso return endpoint "/saml/sso_return"                                                                      | present |
      | info   | User made a request with a session cookie.                                                                                                | present |
      | info   | Created new session.                                                                                                                      |         |
      | info   | User has a session.                                                                                                                       | present |
      | info   | User session matches the session cookie.                                                                                                  | present |
      | notice | Received sso return request                                                                                                               |         |
      | info   | Create sso response                                                                                                                       |         |
      | notice | /Saml response created with id ".*", request ID: ".*"/                                                                                    |         |
      | notice | Invalidate current state and redirect user to service provider assertion consumer url "https://tiqr.dev.openconext.local/demo/sp/acs"     |         |
      | info   | User made a request with a session cookie.                                                                                                |         |
      | info   | Created new session.                                                                                                                      |         |
      | info   | User has a session.                                                                                                                       |         |
      | info   | User session matches the session cookie.                                                                                                  |         |
      | info   | /SAMLResponse with id ".*" was not signed at root level, not attempting to verify the signature of the reponse itself/                    |         |
      | info   | /Verifying signature of Assertion with id ".*"/                                                                                           |         |

