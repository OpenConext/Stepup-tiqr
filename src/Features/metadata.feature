Feature: Metadata endpoint
  In order to know and trust each other
  As a idp
  I need to have metadata endpoint

  Scenario: IDPSSODescriptor metadata must include a X509Certificate
    When I go to "/saml/metadata"
    Then the response should be in XML
    And the XML element "/md:EntityDescriptor/md:IDPSSODescriptor/md:KeyDescriptor/ds:KeyInfo/ds:X509Data/ds:X509Certificate" should contain "MIIEJTCCAw2gAwIBAgIJANug+o++1X5IMA0GCSqGSIb3DQEBCwUAMIGoMQswCQYDVQQ"

  Scenario: Metadata must include a SingleSignOnService
    When I go to "/saml/metadata"
    Then the response should be in XML
    And the XML attribute "Location" on element "/md:EntityDescriptor/md:IDPSSODescriptor/md:SingleSignOnService" should be equal to "https://tiqr.stepup.example.com/saml/sso"
