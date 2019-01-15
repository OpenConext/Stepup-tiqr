## 2.1.2
Changes on the registration page. 

- Add request timeout notification request.
- Fix js IE issue (no const, use var in twig template)
- Add authenticateUrl to authentication page 

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