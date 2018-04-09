Feature: When an user needs to authenticate
  As a service provider
  I need to send an AuthnRequest with a nameID to the identity provider

  Background:
    Given the registration qr code is scanned
    When the user registers the service
    Then we have a registered user
    And I clear the logs

  Scenario: When an user needs to authenticate
    # Service provider demo page
    Given I am on "/demo/sp"
    And I fill in "Subject name id" with my identifier
    When I press "Authenticate user"

    # Tiqr authentication page with qr code
    Then I should see "Login with Tiqr"
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
      | level  | message                                                                                                                                        | sari    |

      # GSSP bundle handling the AuthnRequest
      | notice | Received sso request                                                                                                                           |         |
      | info   | Processing AuthnRequest                                                                                                                        |         |
      | debug  | Extracting public keys for ServiceProvider "https://tiqr.example.com/app_test.php/saml/metadata"                                               |         |
      | debug  | Found "1" keys, filtering the keys to get X509 keys                                                                                            |         |
      | debug  | Found "1" X509 keys, attempting to use each for signature verification                                                                         |         |
      | debug  | /Attempting to verify signature with certificate.*/                                                                                            |         |
      | debug  | Signature VERIFIED                                                                                                                             |         |
      | notice | /AuthnRequest processing complete, received AuthnRequest from "https:\/\/tiqr\.example\.com\/app_test\.php\/saml\/metadata", request ID: ".*"/ |         |
      | info   | AuthnRequest stored in state                                                                                                                   | present |
      | notice | Redirect user to the application authentication route /app_test.php/authentication                                                             | present |

      # Tiqr showing qr image
      | info   | authenticationAction: Verifying if there is a pending authentication request from SP                                                           | present |
      | info   | authenticationAction: Verifying if authentication is finalized                                                                                 | present |
      | info   | authenticationAction: start authentication                                                                                                     | present |
      | info   | authenticationAction: Return authentication page with qr code                                                                                  | present |
      | info   | authenticationQrAction: client request qr image                                                                                                | present |
      | info   | authenticationQrAction: return qr image response                                                                                               | present |

      # Tiqr app
      | notice | loginAction: Login attempt from app                                                                                                            | present |
      | info   | loginAction: Validate user login attempt                                                                                                       | present |
      | info   | loginAction: User login attempt is valid                                                                                                       | present |

      # Tiqr app validating user is authenticated
      | info   | authenticationAction: Verifying if there is a pending authentication request from SP                                                           | present |
      | info   | authenticationAction: Verifying if authentication is finalized                                                                                 | present |
      | info   | authenticationAction: Authentication is finalized, returning to SP                                                                             | present |
      | notice | Application authenticates the user                                                                                                             | present |
      | notice | Created redirect response for sso return endpoint "/app_test.php/saml/sso_return"                                                              | present |

      # GSSP bundle creates saml return response
      | notice | Received sso return request                                                                                                                    | present |
      | info   | Create sso response                                                                                                                            | present |
      | notice | /Saml response created with id ".*", request ID: ".*"/                                                                                         | present |
      | notice | Invalidate current state and redirect user to service provider assertion consumer url "https://tiqr.example.com/app_test.php/demo/sp/acs"      | present |
      | info   | /SAMLResponse with id ".*" was not signed at root level, not attempting to verify the signature of the reponse itself/                         |         |
      | info   | /Verifying signature of Assertion with id ".*"/                                                                                                |         |
      | debug  | Validating the signed element with validator of type "SAML2\Signature\PublicKeyValidator"                                                      |         |
      | debug  | Validation with key "#0" succeeded                                                                                                             |         |

  Scenario: When an user cancels it's authentication
    # Service provider demo page
    Given I am on "/demo/sp"
    And I fill in "Subject name id" with my identifier
    When I press "Authenticate user"

    # Tiqr authentication page with qr code
    Then I should see "Login with Tiqr"
    And I should be on "/authentication"

    # The app
    Then I follow "Cancel"

    # GSSP sso-return endpoint to return to the service provider
    And I should see "Javascript is disabled, please click the button below to proceed"
    And I press "Submit"

    # Service provider AuthNRequest response page
    Then I should see "Cannot process response, preconditions not met: \"Responder/AuthnFailed User cancelled the request\""
    And the logs are:
      | level    | message                                                                                                                                        | sari    |

      # GSSP bundle handling the AuthnRequest
      | notice   | Received sso request                                                                                                                           |         |
      | info     | Processing AuthnRequest                                                                                                                        |         |
      | debug    | Extracting public keys for ServiceProvider "https://tiqr.example.com/app_test.php/saml/metadata"                                               |         |
      | debug    | Found "1" keys, filtering the keys to get X509 keys                                                                                            |         |
      | debug    | Found "1" X509 keys, attempting to use each for signature verification                                                                         |         |
      | debug    | /Attempting to verify signature with certificate.*/                                                                                            |         |
      | debug    | Signature VERIFIED                                                                                                                             |         |
      | notice   | /AuthnRequest processing complete, received AuthnRequest from "https:\/\/tiqr\.example\.com\/app_test\.php\/saml\/metadata", request ID: ".*"/ |         |
      | info     | AuthnRequest stored in state                                                                                                                   | present |
      | notice   | Redirect user to the application authentication route /app_test.php/authentication                                                             | present |

      # Tiqr showing qr image
      | info     | authenticationAction: Verifying if there is a pending authentication request from SP                                                           | present |
      | info     | authenticationAction: Verifying if authentication is finalized                                                                                 | present |
      | info     | authenticationAction: start authentication                                                                                                     | present |
      | info     | authenticationAction: Return authentication page with qr code                                                                                  | present |
      | notice   | cancelAction: User canceled the request                                                                                                        | present |
      | critical | User cancelled the request                                                                                                                     | present |
      | info     | cancelAction: Redirect to sso return endpoint with authentication reject response                                                              | present |
      | notice   | Created redirect response for sso return endpoint "/app_test.php/saml/sso_return"                                                              | present |

      # GSSP bundle creates saml return response
      | notice   | Received sso return request                                                                                                                    | present |
      | info     | Create sso response                                                                                                                            | present |
      | notice   | /Saml response created with id ".*?", request ID: ".*?"/                                                                                       | present |
      | notice   | Invalidate current state and redirect user to service provider assertion consumer url "https://tiqr.example.com/app_test.php/demo/sp/acs"      | present |
