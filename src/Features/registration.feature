Feature: When an user needs to register for a new token
  To register an user for a new token
  As a service provider
  I need to send an AuthnRequest to the identity provider

  Background:
    Given I clear the logs

  Scenario: When an user needs to register for a new token
    Given I am on "/demo/sp"
    When I press "Register user"
    Then I should see "Register new Tiqr account"
    And I should be on "/registration"

    Then I scan the tiqr registration qrcode
    And the user registers the service with notification type "APNS" address: "0000000000111111111122222222223333333333"
    Then we have a registered user

    When I am on "/registration"
    Then I should see "Javascript is disabled, please click the button below to proceed"
    Then I press "Submit"

    Then I should see "urn:oasis:names:tc:SAML:2.0:status:Success"

    And the logs are:
      | level   | message                                                                                                                                   | sari    |

      | info    | User made a request without a session cookie.                                                                                             | present |
      | info    | Created new session.                                                                                                                      |         |
      | info    | User made a request without a session cookie.                                                                                             | present |
      | info    | Created new session.                                                                                                                      |         |
      | info    | User made a request without a session cookie.                                                                                             | present |
      | notice  | Received sso request                                                                                                                      |         |
      | warning | There is already state present, clear previous state                                                                                      |         |
      | info    | Processing AuthnRequest                                                                                                                   |         |
      | notice  | /AuthnRequest processing complete, received AuthnRequest from "https:\/\/tiqr\.dev\.openconext\.local\/saml\/metadata", request ID: ".*"/ |         |
      | info    | AuthnRequest stored in state                                                                                                              |         |
      | notice  | Redirect user to the application registration route /registration                                                                         |         |
      | info    | Created new session.                                                                                                                      |         |
      | info    | User made a request without a session cookie.                                                                                             | present |
      | info    | Verifying if there is a pending registration from SP                                                                                      | present |
      | info    | There is a pending registration                                                                                                           | present |
      | info    | Verifying if registration is finalized                                                                                                    | present |
      | info    | Created new session.                                                                                                                      |         |
      | notice  | Unable to retrieve the state storage value, file not found                                                                                | present |
      | info    | Registration is not finalized return QR code                                                                                              | present |
      | info    | Generating enrollment key                                                                                                                 | present |
      | notice  | /Starting new enrollment session with sessionId .* and userId .*/                                                                         | present |
      | info    | /Setting SARI '.*' for identifier '.*'/                                                                                                   | present |
      | info    | User made a request with a session cookie.                                                                                                | present |
      | info    | Created new session.                                                                                                                      |         |
      | info    | User has a session.                                                                                                                       | present |
      | info    | User session matches the session cookie.                                                                                                  | present |
      | info    | Request for registration QR img                                                                                                           | present |
      | info    | Returning registration QR response                                                                                                        | present |
      | info    | User made a request with a session cookie.                                                                                                | present |
      | info    | Created new session.                                                                                                                      |         |
      | info    | User has a session.                                                                                                                       | present |
      | info    | User session matches the session cookie.                                                                                                  | present |
      | info    | Using "plain" as UserSecretStorage encryption type                                                                                        | present |
      | info    | Using "plain" as UserSecretStorage encryption type                                                                                        | present |
      | notice  | Got GET request to metadata endpoint with enrollment key                                                                                  | present |
      | info    | /Setting SARI '.*' for identifier '.*'/                                                                                                   | present |
      | notice  | Returned metadata response                                                                                                                | present |
      | info    | User made a request with a session cookie.                                                                                                | present |
      | info    | Created new session.                                                                                                                      |         |
      | info    | User has a session.                                                                                                                       | present |
      | info    | User session matches the session cookie.                                                                                                  | present |
      | info    | Using "plain" as UserSecretStorage encryption type                                                                                        | present |
      | info    | Using "plain" as UserSecretStorage encryption type                                                                                        | present |
      | notice  | Got POST with registration response                                                                                                       | present |
      | notice  | Received register action from client with User-Agent "Behat UA" and version ""                                                            | present |
      | info    | Start validating enrollment secret                                                                                                        | present |
      | info    | Setting user secret and notification type and address                                                                                     | present |
      | info    | Finalizing enrollment                                                                                                                     | present |
      | notice  | Enrollment finalized                                                                                                                      | present |
      | notice  | /Writing a trusted-device cookie with fingerprint .*/                                                                                     | present |
      | info    | User made a request with a session cookie.                                                                                                | present |
      | info    | Created new session.                                                                                                                      |         |
      | info    | User has a session.                                                                                                                       | present |
      | info    | User session matches the session cookie.                                                                                                  | present |
      | info    | Verifying if there is a pending registration from SP                                                                                      | present |
      | info    | There is a pending registration                                                                                                           | present |
      | info    | Verifying if registration is finalized                                                                                                    | present |
      | info    | Registration is finalized returning to service provider                                                                                   | present |
      | notice  | /Application sets the subject nameID to .*/                                                                                               | present |
      | notice  | Created redirect response for sso return endpoint "/saml/sso_return"                                                                      | present |
      | info    | User made a request with a session cookie.                                                                                                | present |
      | info    | Created new session.                                                                                                                      |         |
      | info    | User has a session.                                                                                                                       | present |
      | info    | User session matches the session cookie.                                                                                                  | present |
      | notice  | Received sso return request                                                                                                               |         |
      | info    | Create sso response                                                                                                                       |         |
      | notice  | /Saml response created with id ".*", request ID: ".*"/                                                                                    |         |
      | notice  | Invalidate current state and redirect user to service provider assertion consumer url "https://tiqr.dev.openconext.local/demo/sp/acs"     |         |
      | info    | User made a request with a session cookie.                                                                                                |         |
      | info    | Created new session.                                                                                                                      |         |
      | info    | User has a session.                                                                                                                       |         |
      | info    | User session matches the session cookie.                                                                                                  |         |
      | info    | /SAMLResponse with id ".*?" was not signed at root level, not attempting to verify the signature of the reponse itself/                   |         |
      | info    | /Verifying signature of Assertion with id ".*"/                                                                                           |         |


  Scenario: When an user needs to register for a new token but is unable to scan the QR code
    Given I am on "/demo/sp"
    When I press "Register user"
    Then I should see "Register new Tiqr account"
    And I should be on "/registration"

    Then I click the tiqr registration qrcode
    And the user registers the service with notification type "APNS" address: "0000000000111111111122222222223333333333"
    Then we have a registered user

    When I am on "/registration"
    Then I should see "Javascript is disabled, please click the button below to proceed"
    Then I press "Submit"

    Then I should see "urn:oasis:names:tc:SAML:2.0:status:Success"

    And the logs are:
      | level  | message                                                                                                                                   | sari    |

      | info   | User made a request without a session cookie.                                                                                             |         |
      | info   | Created new session.                                                                                                                      |         |
      | info   | User made a request without a session cookie.                                                                                             |         |
      | info   | Created new session.                                                                                                                      |         |
      | info   | User made a request without a session cookie.                                                                                             |         |
      | notice | Received sso request                                                                                                                      |         |
      | info   | Processing AuthnRequest                                                                                                                   |         |
      | notice | /AuthnRequest processing complete, received AuthnRequest from "https:\/\/tiqr\.dev\.openconext\.local\/saml\/metadata", request ID: ".*"/ |         |
      | info   | AuthnRequest stored in state                                                                                                              |         |
      | notice | Redirect user to the application registration route /registration                                                                         |         |
      | info   | Created new session.                                                                                                                      |         |
      | info   | User made a request without a session cookie.                                                                                             | present |
      | info   | Verifying if there is a pending registration from SP                                                                                      | present |
      | info   | There is a pending registration                                                                                                           | present |
      | info   | Verifying if registration is finalized                                                                                                    | present |
      | info   | Created new session.                                                                                                                      |         |
      | notice | Unable to retrieve the state storage value, file not found                                                                                | present |
      | info   | Registration is not finalized return QR code                                                                                              | present |
      | info   | Generating enrollment key                                                                                                                 | present |
      | notice | /Starting new enrollment session with sessionId .* and userId .*/                                                                         | present |
      | info   | /Setting SARI '.*' for identifier '.*'/                                                                                                   | present |
      | info   | User made a request with a session cookie.                                                                                                | present |
      | info   | Created new session.                                                                                                                      |         |
      | info   | User has a session.                                                                                                                       | present |
      | info   | User session matches the session cookie.                                                                                                  | present |
      | info   | Using "plain" as UserSecretStorage encryption type                                                                                        | present |
      | info   | Using "plain" as UserSecretStorage encryption type                                                                                        | present |
      | notice | Got GET request to metadata endpoint with enrollment key                                                                                  | present |
      | info   | /Setting SARI '.*' for identifier '.*'/                                                                                                   | present |
      | notice | Returned metadata response                                                                                                                | present |
      | info   | User made a request with a session cookie.                                                                                                | present |
      | info   | Created new session.                                                                                                                      |         |
      | info   | User has a session.                                                                                                                       | present |
      | info   | User session matches the session cookie.                                                                                                  | present |
      | info   | Using "plain" as UserSecretStorage encryption type                                                                                        | present |
      | info   | Using "plain" as UserSecretStorage encryption type                                                                                        | present |
      | notice | Got POST with registration response                                                                                                       | present |
      | notice | Received register action from client with User-Agent "Behat UA" and version ""                                                            | present |
      | info   | Start validating enrollment secret                                                                                                        | present |
      | info   | Setting user secret and notification type and address                                                                                     | present |
      | info   | Finalizing enrollment                                                                                                                     | present |
      | notice | Enrollment finalized                                                                                                                      | present |
      | notice | /Writing a trusted-device cookie with fingerprint .*/                                                                                     | present |
      | info   | User made a request with a session cookie.                                                                                                | present |
      | info   | Created new session.                                                                                                                      |         |
      | info   | User has a session.                                                                                                                       | present |
      | info   | User session matches the session cookie.                                                                                                  | present |
      | info   | Verifying if there is a pending registration from SP                                                                                      | present |
      | info   | There is a pending registration                                                                                                           | present |
      | info   | Verifying if registration is finalized                                                                                                    | present |
      | info   | Registration is finalized returning to service provider                                                                                   | present |
      | notice | /Application sets the subject nameID to .*/                                                                                               | present |
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


  Scenario: When an user needs to cancel the registration
    Given I am on "/demo/sp"

    # Tiqr page with QR code.
    When I press "Register user"
    Then I should see "Register new Tiqr account"
    And I should be on "/registration"
    Then I follow "Cancel"

    # GSSP return endpoint.
    And I should see "Javascript is disabled, please click the button below to proceed"
    And I press "Submit"

    # Service prodvider
    Then I should see "Cannot process response, preconditions not met: \"Responder/AuthnFailed User cancelled the request\""

    And the logs are:
      | level    | message                                                                                                                                   | sari    |

      | info     | User made a request without a session cookie.                                                                                             |         |
      | info     | Created new session.                                                                                                                      |         |
      | info     | User made a request without a session cookie.                                                                                             |         |
      | info     | Created new session.                                                                                                                      |         |
      | info     | User made a request without a session cookie.                                                                                             |         |
      | notice   | Received sso request                                                                                                                      |         |
      | info     | Processing AuthnRequest                                                                                                                   |         |
      | notice   | /AuthnRequest processing complete, received AuthnRequest from "https:\/\/tiqr\.dev\.openconext\.local\/saml\/metadata", request ID: ".*"/ |         |
      | info     | AuthnRequest stored in state                                                                                                              |         |
      | notice   | Redirect user to the application registration route /registration                                                                         |         |
      | info     | Created new session.                                                                                                                      |         |
      | info     | User made a request without a session cookie.                                                                                             | present |
      | info     | Verifying if there is a pending registration from SP                                                                                      | present |
      | info     | There is a pending registration                                                                                                           | present |
      | info     | Verifying if registration is finalized                                                                                                    | present |
      | info     | Created new session.                                                                                                                      |         |
      | notice   | Unable to retrieve the state storage value, file not found                                                                                | present |
      | info     | Registration is not finalized return QR code                                                                                              | present |
      | info     | Generating enrollment key                                                                                                                 | present |
      | notice   | /Starting new enrollment session with sessionId .* and userId .*/                                                                         | present |
      | info     | /Setting SARI '.*' for identifier '.*'/                                                                                                   | present |
      | info     | User made a request with a session cookie.                                                                                                | present |
      | info     | Created new session.                                                                                                                      |         |
      | info     | User has a session.                                                                                                                       | present |
      | info     | User session matches the session cookie.                                                                                                  | present |
      | notice   | User cancelled the request                                                                                                                | present |
      | critical | User cancelled the request                                                                                                                | present |
      | info     | Redirect to sso return endpoint with registration reject response                                                                         | present |
      | notice   | Created redirect response for sso return endpoint "/saml/sso_return"                                                                      | present |
      | info     | User made a request with a session cookie.                                                                                                | present |
      | info     | Created new session.                                                                                                                      |         |
      | info     | User has a session.                                                                                                                       | present |
      | info     | User session matches the session cookie.                                                                                                  | present |
      | notice   | Received sso return request                                                                                                               |         |
      | info     | Create sso response                                                                                                                       |         |
      | notice   | /Saml response created with id ".*?", request ID: ".*?"/                                                                                  |         |
      | notice   | Invalidate current state and redirect user to service provider assertion consumer url "https://tiqr.dev.openconext.local/demo/sp/acs"     |         |
      | info     | User made a request with a session cookie.                                                                                                |         |
      | info     | Created new session.                                                                                                                      |         |
      | info     | User has a session.                                                                                                                       |         |
      | info     | User session matches the session cookie.                                                                                                  |         |


#  Scenario: When the user is redirected from an unknown service provider he should see an error page
#    Given a normal SAML 2.0 AuthnRequest form a unknown service provider
#    Then the response status code should be 406
#    And I should see "Error - Unknown service provider"
#    And the logs are:
#      | level  | message                 | sari |
#      | notice | Received sso request    |      |
#      | info   | Processing AuthnRequest |      |

#  Scenario: When an user request the sso endpoint without AuthnRequest the request should be denied
#    When I am on "/saml/sso"
#    Then the response status code should be 406
#    And I should see "Something went wrong. Please try again."
#    And the logs are:
#      | level  | message                 | sari |
#      | notice | Received sso request    |      |
#      | info   | Processing AuthnRequest |      |
