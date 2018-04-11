<?php
/**
 * Copyright 2018 SURFnet B.V.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace AppBundle\Tiqr;

use AppBundle\Tiqr\Response\AuthenticationResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

interface TiqrServiceInterface
{
    /**
     * NOTE: this call will generate literal PNG data. This makes it harder to intercept the enrolment key
     * This is also the reason why enrolment cannot be performed an the phone (by clicking the image, as with authN)
     * as it would expose the enrolment key to the client in plaintext next to the "PNG-encoded" version.
     *
     * @param string $metadataURL
     * @return StreamedResponse
     */
    public function createRegistrationQRResponse($metadataURL);

    /**
     * Get a temporary enrollment secret to be able to securely post a user
     * secret.
     *
     * As part of the enrollment process the phone will send a user secret.
     * This shared secret is used in the authentication process. To make sure
     * user secrets can not be posted by malicious hackers, a secret is
     * required. This secret should be included in the enrollmentUrl that is
     * passed to the getMetadata function.
     *
     * @param string $key
     *      The enrollmentKey generated at the start of the enrollment process.
     * @return String The enrollment secret
     */
    public function getEnrollmentSecret($key);

    /**
     * Starts and generates an enrollment key.
     *
     * @return string
     */
    public function generateEnrollmentKey();

    /**
     * Retrieve the metadata for an enrollment session.
     *
     * When the phone calls the url that you have passed to
     * generateEnrollmentQR, you must provide it with the output
     * of this function. (Don't forget to json_encode the output.)
     *
     * Note, you can call this function only once, as the enrollment session
     * data will be destroyed as soon as it is retrieved.
     *
     * @param string $key
     *      The enrollmentKey that the phone has
     *      posted along with its request.
     * @param string $loginUri
     *      The url you provide to the phone to
     *      post authentication responses
     * @param string $enrollmentUrl
     *      The url you provide to the phone to post
     *      the generated user secret. You must include
     *      a temporary enrollment secret in this URL
     *      to make this process secure. This secret
     *      can be generated with the
     *      getEnrollmentSecret call.
     *
     * @return array An array of metadata that the phone needs to complete
     *               enrollment. You must encode it in JSON before you send
     *               it to the phone.
     */
    public function getEnrollmentMetadata($key, $loginUri, $enrollmentUrl);

    /**
     * Validate if an enrollmentSecret that was passed from the phone is valid.
     *
     * @param $enrollmentSecret
     *      The secret that the phone posted; it must match
     *      the secret that was generated using
     *      getEnrollmentSecret earlier in the process.
     *
     * @return string|false
     *      The userId of the user that was being enrolled if the
     *      secret is valid. This userId should be used to store the
     *      user secret that the phone posted.
     *      If the enrollmentSecret is invalid, false is returned.
     */
    public function validateEnrollmentSecret($enrollmentSecret);

    /**
     * Finalize the enrollment process.
     * If the user secret was posted by the phone, was validated using
     * validateEnrollmentSecret AND if the secret was stored securely on the
     * server, you should call finalizeEnrollment. This clears some enrollment
     * temporary pieces of data, and sets the status of the enrollment to
     * finalized.
     * @param String The enrollment secret that was posted by the phone. This
     *               is the same secret used in the call to
     *               validateEnrollmentSecret.
     * @return boolean True if succesful
     */
    public function finalizeEnrollment($enrollmentSecret);

    /**
     * Generate an authentication challenge URL.
     * This URL can be used to link directly to the authentication
     * application, for example to create a link in a mobile website on the
     * same device as where the application is installed
     *
     * @param string $nameId
     *
     * @return string
     */
    public function startAuthentication($nameId);

    /**
     * @return boolean
     */
    public function isAuthenticated();

    /**
     * @return StreamedResponse
     */
    public function createAuthenticationQRResponse();

    public function authenticationUrl();

    /**
     * @param TiqrUserInterface $user
     * @param string $response
     * @param string $sessionKey
     *
     * @return AuthenticationResponse
     */
    public function authenticate(TiqrUserInterface $user, $response, $sessionKey);

    /**
     * Returns the current enrollment status.
     *
     * @return string
     */
    public function getEnrollmentStatus();

    /**
     * If the user is enrolled
     *
     * @return boolean
     */
    public function enrollmentFinalized();

    /**
     * Returns the current id of the enrolled user.
     * @return string
     */
    public function getUserId();

    /**
     * Return the authentication session id.
     *
     * @return string
     */
    public function getAuthenticationSessionKey();
}
