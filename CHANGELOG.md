# Stepup-tiqr

## 4.0.0
* The tiqr code was upgraded to allow use of Symfony 6.4
* Many other dependencies were upgraded in the process
* We started to use the Openconext-devconf dev-env

## 3.4.6
* Update Tiqr library to 3.0.2 (fixes #164)
* Update dependencies

## 3.4.5
* Update Tiqr library to 3.0.1
* Re-add Logging of Tiqr client information introduced in 3.1.4 (#155)
* Update twig (#154)

## 3.4.4
* Update Tiqr library to 3.0-rc2

## 3.4.0

**Feature**
* Update Tiqr library to 3.0. This version has improved logging and error handling

**Breaking changes**
* This version requires an update to the user table when using the UserStorage PDO
  See config/db/mysql-upgrade-user-table-3.4.sql
* The usersecretstorage in config/legacy/parameters.yaml must now be explicitly configured,
  it no longer implicitly uses the configuration from the userstorage.

## 3.3.1
**Maintenance**
* TravisCI and Ant have been replaced with GithubActions workflow and Composer scripts #143

## 3.3.0

**Feature**
* Tiqr server library upgrade #145
* Remove GCM and always use FCM instead #144
* Log tiqr client version information #147
* Catch the tiqr-server-libphp ReadWriteExceptions #146

**Chores**
* Create state table #137
* Update packages #142

## 3.2.1
- Setup Github Actions tag release workflow

## 3.1.2
- Added browserlist entry in package.json to ensure IE 11 support
- Re-enable the Jest tests that where disabled in 3.1.1

## 3.1.1
**Bugfix**
* Prevent session data collisions during enrollment

## 3.1.0.1
- Intermediate IE11 fix to resolve the Babel IE11 compatibility issues

## 3.1.0
**Feature**
* Add the enrollment link on the QR code #128 #129

**Chores**
 - Updated travis runtime environment variables #119
 - Update monitor-bundle to add opcache info #126

**Security updates**
Multiple security updates have been installed in this new release. But one issue remains unresolved. This is the entire
removal of the vulnerable version of ansi-regex. This should be fixed in the near future by webpack and its peers. As
this is strictly a build-time only issue. It is considered an acceptable risk at this point.

## 3.0.4
- Fix favicon after SF4 update
- Update dependencies
- Disable unused fragments
- Fix error message for broken accounts
- Add X-UA-Compatible meta tag

## 3.0.3
 - Add monitoring endpoints /health and /info
 - Update dependencies
 - Move from security-checker to local-php-security-checker
 - Fix duplicate push-notifications
 - Fix rare reloading-after-authentication issue

## 3.0.2
* Use location.reload() to prevent rare chrome issue

## 3.0.1
 * Move parameters to legacy folder

## 3.0.0
* Drop php 5.* support
* Upgrade to SF4
* Update travis configuration
* Remove obsolete pre archive command
* Enable php code style checking
* Re-enabled running unittests
* Use syslog for logging in production

## 2.1.15
"This is a security release that will harden the application against CVE 2019-3465
 * Upgrade xmlseclibs to version 3.0.4
 
## 2.1.14
 * Use FCM always as fallback for GCM #80
 
## 2.1.13
 * Update symfony/symfony and symfony/phpunit-bridge #79

## 2.1.10 .. 2.1.12
This release adds some JavaScript browser support for older IE browsers. This should result in the ability to perform tiqr registrations and authentications in IE >= 8.

- Add ECMAScript 3 support #75
- Spinner support for non SVG SMIL supporting browsers #77

## 2.1.9
A bugfix release where Firebase push notifications would contain a
duplicate text. See #74 for more details.

## 2.1.3 .. 2.1.8
These releases added Firebase push notification support to Stepup-tiqr 
and fixed the security checker.

Support Firebase fallback. Tiqr needs a fallback mechanism to support 
Firebase as fallback notification mechanism in case GCM fails.

## 2.1.2
Changes on the registration page. 

- Add request timeout notification request.
- Fix js IE issue (no const, use var in twig template)
- Add authenticateUrl to authentication page 
- Textual changes
- Fix authentication status endpoint

## 2.1.1
Changes on the registration page. 

- inline JS logic converted to typescript.
- Stop polling for status when an error or session is expired.

## 2.1.0

- Show styled error page for routes authentication, registration, cancel page when no AuthNRequest is active.

Changes on the authentication page. 

- Stop polling for status when an error or authentication token is expired.  
- Disabled automatic page refresh when authentication token is expired.
- Move logic for authentication request to client-side (fixes timeouts on push notification name resolvers).

## 2.0.1
The most notable changes are
- A user agent pattern can be set to ensure the users tiqr app is of the correct vendor. 
- Many user experience upgrades have been applied. Like: QR code size optimization, hide mouse cursor on the QR code and 
  many more.
- Locale cookie is no longer set by tiqr but is still sensitive to a local cookie for setting the correct locale.
- Security audit findings are addressed in this release, most changes have been fixed in the GSSP bundle which is updated 
  in this release. 
- The monitor bundle was added to tiqr.

## Releases 1.1.0 .. 2.0.0
No release notes available for these releases.

## 1.1.0
Remove phantomjs and use goutte instead for webtest
   
## 1.0.0
initial release
