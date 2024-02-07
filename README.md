Stepup-tiqr
===========

[![Build Status](https://travis-ci.org/OpenConext/Stepup-tiqr.svg?branch=develop)](https://travis-ci.org/OpenConext/Stepup-tiqr)

GSSP implementation of Tiqr. [https://tiqr.org/documentation/](https://tiqr.org/documentation/)

Project is based on example GSSP project [https://github.com/OpenConext/Stepup-gssp-example](https://github.com/OpenConext/Stepup-gssp-example)

Locale user preference
----------------------

The default locale is based on the user agent. When the user switches its locale the selected preference is stored inside a
browser cookie (stepup_locale). The cookie is set on naked domain of the requested domain (for tiqr.dev.openconext.local this is dev.openconext.local).

Authentication and registration flows
-------------------------------------

The application provides internal (SpBundle) and a remote service provider. Instructions for this are given 
on the homepage of this Tiqr project [Homepage](https://tiqr.dev.openconext.local/).

![flow](docs/flow.png)
<!---
regenerate docs/flow.png with `plantum1 README.md` or with http://www.plantuml.com/plantuml
@startuml docs/flow
actor User
participant "Service provider" as SP
box "Stepup Tiqr"
participant "GSSP Bundle" as IdP
participant "Tiqr implementation" as TiqrSF
end box
User -> SP: Register/Authenticate
SP -> IdP: Send AuthnRequest
activate IdP
IdP -> TiqrSF: Redirect to SecondFactor endpoint
TiqrSF -> TiqrSF: <Tiqr logic>
TiqrSF -> IdP: Redirect to SSO Return endpoint
IdP -> SP: AuthnRequest response
deactivate IdP
SP -> User: User registered/Authenticated
@enduml
--->

Tiqr registration
-----------------

![flow](docs/tiqr_registration.png)
<!---
regenerate docs/tiqr_registration.png with `plantum1 README.md` or with http://www.plantuml.com/plantuml
@startuml docs/tiqr_registration
actor User
participant "Website" as Site
participant "App" as App
participant "Api" as Api
activate Site
Site -> User: Show QR code
App -> Site: Scan the registration code
deactivate Site
activate App
App -> Api: Request the metadata endpoint 
App -> User: Asks for verification code
App -> Api: Registers user with secret and OTP
deactivate App
activate Site
Site -> Api: Asks the Api if the user is registered
Site -> User: Registration done
deactivate Site
@enduml
--->

Development environment
======================

To get started, first setup the development environment. The dev env is a virtual machine. Every task described here is required to run
from that machine.  

Requirements
-------------------
- ansible 2.x
- vagrant 1.9.x
- vagrant-hostsupdater
- Virtualbox
- ansible-galaxy

Install
=======

See one of the following guides:

[Development guide](docs/development.md)

[Production installation](docs/deployment.md)

Tests and metrics
======================

To run all required test you can run the following commands from the dev env:

```bash 
    composer test 
    composer behat
```

Every part can be run separately. Check "scripts" section of the composer.json file for the different options.

Test Tiqr Api's
---------------

Demo sp is available on  [https://tiqr.dev.openconext.local/demo/sp]()

Fetch registration link automatically from /app_dev.php/registration/qr/dev

``` ./bin/console test:registration <./qr_file.png>```  

``` ./bin/console test:authentication <./qr_file.png>```  

Authentication can also be done in 'offline' mode, so you need to fill in your 'one time password'.

``` ./bin/console test:authentication --offline=true ./<qr_file.png>```  

User storage
============
Currently we support three user storage solutions. Which are file system storage, ldap and database storage. The 
filesystem storage is used by default and stores the registered users in the `/var/userdb.json` file. 

Database storage
----------------
To use the database storage you will need to change some settings:

In the `parametes.yml`, in the `tiqr_library_options.storage.userstorage` section configure: 

```yaml
tiqr_library_options:        
    storage:
        userstorage:
            type: pdo
            arguments:
                table: user
                dsn: 'mysql:host=tiqr.stepup.example.com;dbname=tiqr'
                username: tiqr-user
                password: tiqr-secret
```

The database schema can be found here: `app/Resources/db/mysql-create-tables.sql`

Filesystem storage
------------------
Or if you want to use the filesystem storage use this:

```yaml
tiqr_library_options:        
    storage:
        userstorage:
            type: 'file'
            arguments:
              path: '/tmp'
              encryption: 'dummy' # mcrypt is also supported, dummy will not encrypt the entries in the user storage file
```

LDAP storage
------------
Finally to use the LDAP backend provide the following options:

```yaml
tiqr_library_options:        
    storage:
        userstorage:
            type: 'ldap'
            # The argument values equal the default values set when the arguments are omitted. So all arguments are
            # optional.
            arguments:
                userClass: 'tiqrPerson'
                dnPattern: '%s'
                idAttr: 'dn'
                displayNameAttr: 'sn'
                secretAttr: 'tiqrSecret'
                notificationTypeAttr: 'tiqrNotificationType'        
                notificationAddressAttr: 'tiqrNotificationAddress'        
                isBlockedAttr: 'tiqrIsBlocked'
                loginAttemptsAttr: 'tiqrLoginAttempts'  
                temporaryBlockAttemptsAttr: 'tiqrTemporaryBlockAttempts'
                temporaryBlockTimestampAttr: 'tiqrTemporaryBlockTimestamp'
                attributes: null
```

# Release strategy
Please read: https://github.com/OpenConext/Stepup-Deploy/wiki/Release-Management fro more information on the release strategy used in Stepup projects.

# How the Stepup-tiqr uses the Tiqr library
The Tiqr server's purpose is to facilitate Tiqr authentications. In doing so communicating with the Tiqr app. Details about this communication flow can be found in the flow above. Here you will find a communication diagram for enrollment and authentication.

The following code examples show some of the concepts that are used during authentication from the web frontend. It does not include the communication with the Tiqr client (app).

```php
# 1. The name id (username) of the user is used to identify that specific user in Tiqr. 
#    In the case of Stepup-Tiqr (SAML based) we get the NameId from the SAML 2.0 AuthnRequest
#
# Example below is pseudocode you might write in your controller dealing with an authentication request
$nameId = $this->authenticationService->getNameId();

# The request id of the SAML AuthnRequest message, used to match the originating authentication request with the Tiqr authentication
$requestId = $this->authenticationService->getRequestId();
```

```php
# 2. Next you can do some verifications on the user, is it found in tiqr-server user storage?
#    Is it not locked out temporarily?
#
# Example below is pseudocode you might write in your controller dealing with an authentication request
$user = $this->userRepository->getUser($nameId);
if ($this->authenticationRateLimitService->isBlockedTemporarily($user)) {
    throw new Exception('You are locked out of the system');
}

$this->startAuthentication($nameId, $requestId)
public function startAuthentication($nameId, $requestId)
{
    # Authentication is started by providing the NameId and the PHP session id
    $sessionKey = $this->tiqrService->startAuthenticationSession($nameId, $this->session->getId());
    # The Service (Tiqr_Service) generates a session key which is stored in the state storage, but also returned to
    # persist in the Tiqr server implementation. 
    $this->session->set('sessionKey', $sessionKey);
    $this->storeRequestIdForNameId($sessionKey, $requestId);
    # Creates an authentication challenge URL. It links directly to the application 
    return $this->tiqrService->generateAuthURL($sessionKey);
}
```

```php
# 3. The tiqr server implementation now must wait for the Tiqr App to finalize its authentication with the user.
#    In the Stepup-Tiqr implementation, we do this by polling the tiqr server for the atuthentication status.
# Example below is pseudocode

# Javascript
function pollTiqrStatus() {
    getTiqrStatus()
    setTimeout(refresh, 5000);
}
pollTiqrStatus();

# In the PHP application:
$isAuthenticated = $this->tiqrService->getAuthenticatedUser($this->session->getId());
if ($isAuthenticated) {
    # Your controller can now go to the next action, maybe send back a successful SamlResponse, or signal otherwise
    # that the authentication succeeded. 
    return $successResponse;
}
# And deal with the non happy flow

if ($isExpired) {
    return $errorResponse;
}

if ($otherErrorConddition) {
    # ...
}
```

Other resources
===============

 - [Developer documentation](docs/index.md)
 - [Issue tracker](https://www.pivotaltracker.com/n/projects/1163646)
 - [License](LICENSE)
 - [Tiqr library](https://github.com/SURFnet/tiqr-server-libphp)
 - [Library documentation](https://tiqr.org/documentation/) 
 - [Tiqr config parameters](https://github.com/SURFnet/simplesamlphp-module-authtiqr)

