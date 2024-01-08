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

namespace App\Tiqr;

use App\Exception\TiqrServerRuntimeException;
use App\Tiqr\Response\AuthenticationResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

interface TiqrServiceInterface
{
    /**
     * Return a stream with the PNG image data of a QR code with the enrollment url and enrollment key
     *
     * @see \Tiqr_Service::generateEnrollmentQR()
     */
    public function createRegistrationQRResponse(string $metadataURL): StreamedResponse;

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
     * @param string $enrollmentEnrollmentKey
     *      The enrollmentKey generated at the start of the enrollment process.
     * @param string $sari The unique identifier of this enrollment
     * @return String The enrollment secret
     * @throws TiqrServerRuntimeException
     *@see \Tiqr_Service::getEnrollmentSecret()
     *
     */
    public function getEnrollmentSecret(string $enrollmentEnrollmentKey, string $sari): string;

    /**
     * Start a new enrollment session for enrolling a new tiqr user
     * Generates an enrollment key and a tiqr userID
     *
     * @see \Tiqr_Service::startEnrollmentSession()
     *
     * @param string $sari The unique identifier of this enrollment
     *
     * @return string the enrollment key
     * @throws TiqrServerRuntimeException
     */
    public function generateEnrollmentKey(string $sari): string;

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
     * @see \Tiqr_Service::getEnrollmentMetadata()
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
     *      a temporarily enrollment secret in this URL
     *      to make this process secure. This secret
     *      can be generated with the
     *      getEnrollmentSecret call.
     *
     * @return array<string, mixed> An array of metadata that the phone needs to complete
     *               enrollment. You must encode it in JSON before you send
     *               it to the phone.
     *
     * @throws TiqrServerRuntimeException
     */
    public function getEnrollmentMetadata(string $key, string $loginUri, string $enrollmentUrl): array;

    /**
     * Validate if an enrollmentSecret that was passed from the phone is valid.
     *
     * @see \Tiqr_Service::validateEnrollmentSecret()
     *
     * @param string $enrollmentSecret
     *      The secret that the phone posted; it must match
     *      the secret that was generated using
     *      getEnrollmentSecret earlier in the process.
     *
     * @return string
     *      The userId of the user that was being enrolled if the
     *      secret is valid. This userId should be used to store the
     *      user secret that the phone posted.
     *
     * @throws TiqrServerRuntimeException
     */
    public function validateEnrollmentSecret(string $enrollmentSecret): string;

    /**
     * Finalize the enrollment process.
     * If the user secret was posted by the phone, was validated using
     * validateEnrollmentSecret AND if the secret was stored securely on the
     * server, you should call finalizeEnrollment. This clears some enrollment
     * temporarily pieces of data, and sets the status of the enrollment to
     * finalized.
     *
     * @see \Tiqr_Service::finalizeEnrollment()
     *
     * @param string $enrollmentSecret The enrollment secret that was posted by the phone. This
     *               is the same secret used in the call to
     *               validateEnrollmentSecret.
     * @throws TiqrServerRuntimeException
     */
    public function finalizeEnrollment(string $enrollmentSecret): void;

    /**
     * Generate an authentication challenge URL.
     * This URL can be used to link directly to the authentication
     * application, for example to create a link in a mobile website on the
     * same device as where the application is installed
     *
     * @see \Tiqr_Service::startAuthenticationSession()
     * @see \Tiqr_Service::generateAuthURL()
     *
     *
     * @return string the generated authentication URL
     * @throws TiqrServerRuntimeException
     */
    public function startAuthentication(string $userId, string $sari): string;

    /**
     * @return bool true when there is an authenticated user in the current session,
     *              false otherwise
     *
     * @see \Tiqr_Service::getAuthenticatedUser()
     */
    public function isAuthenticated(): bool;

    /**
     * Response with authentication challenge QR code.
     *
     * This URL can be used to link directly to the authentication
     * application, for example to create a link in a mobile website on the
     * same device as where the application is installed
     *
     * @see \Tiqr_Service::generateAuthQR()
     *
     * @throws TiqrServerRuntimeException
     */
    public function createAuthenticationQRResponse(): StreamedResponse;

    /**
     * Generate an authentication challenge URL.
     *
     * This URL can be used to link directly to the authentication
     * application, for example to create a link in a mobile website on the
     * same device as where the application is installed
     *
     * @see \Tiqr_Service::generateAuthURL()
     * @throws TiqrServerRuntimeException
     *
     */
    public function authenticationUrl(): string;

    /**
     *
     * @throws TiqrServerRuntimeException
     */
    public function authenticate(TiqrUserInterface $user, string $response, string $sessionKey): AuthenticationResponse;

    /**
     * Returns the current enrollment status.
     * @see \Tiqr_Service::getEnrollmentStatus()
     *
     * @throws TiqrServerRuntimeException
     */
    public function getEnrollmentStatus(): int;

    /**
     * Check the user's enrollment process is finalized
     * @see \Tiqr_Service::finalizeEnrollment()
     *
     * @return bool true when the enrollment status is Tiqr_Service::ENROLLMENT_STATUS_FINALIZED
     * @throws TiqrServerRuntimeException
     */
    public function enrollmentFinalized(): bool;

    /**
     * Returns the current id of the enrolled user.
     */
    public function getUserId(): string;

    /**
     * Return the authentication session id.
     */
    public function getAuthenticationSessionKey(): string;

    /**
     * Sent a push notification to a tiqr client to start an authentication
     *
     * During enrollment, and after each successful authentication the tiqr client
     * return the $notificationAddress and the $notificationType that are required
     * to sent push notifications to the device.
     *
     * @throws TiqrServerRuntimeException
     *
     */
    public function sendNotification(string $notificationType, string $notificationAddress): void;

    /**
     * @param string $identifier Enrollment key or session key
     *
     * @return string with the sari that was previously set for $identifier,
     *                empty string when no sari could be found for $identifier
     *
     * Does not throw
     */
    public function getSariForSessionIdentifier(string $identifier): string;
}
