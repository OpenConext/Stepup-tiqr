Stepup-tiqr
===========

[![Build Status](https://travis-ci.org/OpenConext/Stepup-tiqr.svg?branch=develop)](https://travis-ci.org/OpenConext/Stepup-tiqr)

GSSP implementation of Tiqr. [https://tiqr.org/documentation/](https://tiqr.org/documentation/)

Project is based on example GSSP project [https://github.com/OpenConext/Stepup-gssp-example](https://github.com/OpenConext/Stepup-gssp-example)

Locale user preference
----------------------

The default locale is based on the user agent. When the user switches its locale the selected preference is stored inside a
browser cookie (stepup_locale). The cookie is set on naked domain of the requested domain (for tiqr.example.com this is example.com).

Authentication and registration flows
-------------------------------------

The application provides internal (SpBundle) and a remote service provider. Instructions for this are given 
on the homepage of this Tiqr project [Homepage](https://tiqr.example.com/app_dev.php/).

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

``` ansible-galaxy install -r ansible/requirements.yml -p ansible/roles/```

Using the `-c` flag can be used to disable ssl verification on the install command.

``` vagrant up ```

Go to the directory inside the VM:

``` vagrant ssh ```

``` cd /vagrant ```

Install composer dependencies:

``` composer install ```

Build frontend assets:

``` composer encore dev ``` or ``` composer encore production ``` for production 

If everything goes as planned you can go to:

[https://tiqr.example.com](https://tiqr.example.com)

You might need to add your IP address to the list of allowed remote address in `web/app_dev.php`.

Debugging
---------

Xdebug is configured when provisioning your development Vagrant box. 
It's configured with auto connect IDE_KEY=phpstorm.

Demo sp is available on  [https://tiqr.example.com/app_dev.php/demo/sp]()

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

Demo sp is available on  [https://tiqr.example.com/app_dev.php/demo/sp]()

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
                dsn: 'mysql:host=tiqr.example.com;dbname=tiqr'
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

Other resources
======================

 - [Developer documentation](docs/index.md)
 - [Issue tracker](https://www.pivotaltracker.com/n/projects/1163646)
 - [License](LICENSE)
 - [Tiqr library](https://github.com/SURFnet/tiqr-server-libphp)
 - [Library documentation](https://tiqr.org/documentation/) 
 - [Tiqr config parameters](https://github.com/SURFnet/simplesamlphp-module-authtiqr)
