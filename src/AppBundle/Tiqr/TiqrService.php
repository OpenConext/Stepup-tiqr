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

use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Wrapper around the legacy Tiqr service.
 */
final class TiqrService implements TiqrServiceInterface
{
    /**
     * @var \Tiqr_Service
     */
    private $tiqrService;
    private $session;

    public function __construct($tiqrService, SessionInterface $session)
    {
        $this->tiqrService = $tiqrService;
        $this->session = $session;
    }

    public function exitWithEnrollmentQR($metadataURL)
    {
        $this->tiqrService->generateEnrollmentQR($metadataURL);
        exit(200);
    }

    public function getEnrollmentSecret($key)
    {
        return $this->tiqrService->getEnrollmentSecret($key);
    }

    /**
     * Starts and generates an enrollment key.
     *
     * @return string
     */
    public function generateEnrollmentKey()
    {
        $id = $this->generateId();
        return $this->tiqrService->startEnrollmentSession($id, 'SURFconext', $this->session->getId());
    }

    public function getEnrollmentMetadata($key, $loginUri, $enrollmentUrl)
    {
        return $this->tiqrService->getEnrollmentMetadata($key, $loginUri, $enrollmentUrl);
    }

    public function validateEnrollmentSecret($enrollmentSecret)
    {
        return $this->tiqrService->validateEnrollmentSecret($enrollmentSecret);
    }

    public function finalizeEnrollment($enrollmentSecret)
    {
        $this->tiqrService->finalizeEnrollment($enrollmentSecret);
    }

    /**
     * Currently the legacy way to generate the user Tiqr id.
     *
     * TODO:maybe use something like UUID?
     *
     * @param int $length
     *
     * @return string
     */
    private function generateId($length = 4)
    {
        return base_convert(time(), 10, 36).'-'.base_convert(mt_rand(0, pow(36, $length)), 10, 36);
    }
}
