<?php

declare(strict_types = 1);

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

namespace Surfnet\Tiqr\Controller;

use Exception;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Surfnet\GsspBundle\Service\AuthenticationService;
use Surfnet\GsspBundle\Service\StateHandlerInterface;
use Surfnet\Tiqr\Exception\NoActiveAuthenrequestException;
use Surfnet\Tiqr\Exception\UserNotFoundException;
use Surfnet\Tiqr\Exception\UserPermanentlyBlockedException;
use Surfnet\Tiqr\Exception\UserTemporarilyBlockedException;
use Surfnet\Tiqr\Tiqr\AuthenticationRateLimitServiceInterface;
use Surfnet\Tiqr\Tiqr\Exception\UserNotExistsException;
use Surfnet\Tiqr\Tiqr\Response\AuthenticationResponse;
use Surfnet\Tiqr\Tiqr\Response\RateLimitedAuthenticationResponse;
use Surfnet\Tiqr\Tiqr\TiqrServiceInterface;
use Surfnet\Tiqr\Tiqr\TiqrUserInterface;
use Surfnet\Tiqr\Tiqr\TiqrUserRepositoryInterface;
use Surfnet\Tiqr\WithContextLogger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class AuthenticationController extends AbstractController
{
    public function __construct(
        private readonly AuthenticationService $authenticationService,
        private readonly StateHandlerInterface $stateHandler,
        private readonly TiqrServiceInterface $tiqrService,
        private readonly TiqrUserRepositoryInterface $userRepository,
        private readonly AuthenticationRateLimitServiceInterface $authenticationRateLimitService,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @throws InvalidArgumentException
     * @throws NoActiveAuthenrequestException
     * @throws UserNotFoundException
     * @throws Exception
     */
    #[Route(path: '/authentication', name: 'app_identity_authentication', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        $nameId = $this->authenticationService->getNameId();
        $sari = $this->stateHandler->getRequestId();
        $logger = WithContextLogger::from($this->logger, ['nameId' => $nameId, 'sari' => $sari]);

        $logger->info('Verifying if there is a pending authentication request from SP');

        // Do we have a valid GSSP authentication AuthnRequest in this session?
        if (!$this->authenticationService->authenticationRequired()) {
            $logger->error('There is no pending authentication request from SP');

            throw new NoActiveAuthenrequestException();
        }

        try {
            $user = $this->userRepository->getUser($nameId);
        } catch (UserNotExistsException $exception) {
            $logger->error(sprintf(
                'User with nameId "%s" not found, error "%s"',
                $nameId,
                $exception->getMessage()
            ));

            throw new UserNotFoundException();
        }

        // Verify if user is blocked.
        $logger->info('Verify if user is blocked');
        $blockedTemporarily = $this->authenticationRateLimitService->isBlockedTemporarily($user);
        $blockedPermanently = $this->authenticationRateLimitService->isBlockedPermanently($user);
        if ($blockedTemporarily || $blockedPermanently) {
            $logger->info('User is blocked');

            return $this->showUserIsBlockedErrorPage($blockedPermanently);
        }

        // Handle one time password
        if ($request->get('otp') !== null) {
            $logger->info('Handling otp');
            $response = $this->authenticationRateLimitService->authenticate(
                $this->tiqrService->getAuthenticationSessionKey(),
                $user,
                $request->get('otp')
            );
            if (!$response->isValid()) {
                return $this->handleInvalidResponse($user, $response, $logger);
            }
        }

        $logger->info('Verifying if authentication is finalized');

        // Are we already logged in with tiqr?
        if ($this->tiqrService->isAuthenticated()) {
            $logger->info('Authentication is finalized, returning to SP');
            $this->authenticationService->authenticate();

            return $this->authenticationService->replyToServiceProvider();
        }

        // Start authentication process.
        try {
            $logger->info('Start authentication');
            $this->tiqrService->startAuthentication(
                $nameId,
                $sari
            );
        } catch (Exception $e) {
            $logger->error(sprintf(
                'Failed to start authentication "%s"',
                $e->getMessage()
            ));
            $logger->info('Return authentication failed response');
            $this->authenticationService->reject($e->getMessage());

            return $this->authenticationService->replyToServiceProvider();
        }

        $logger->info('Return authentication page with QR code');

        return $this->render('default/authentication.html.twig', [
            // TODO: Add something identifying the authentication session to the authenticateUrl
            'authenticateUrl' => $this->tiqrService->authenticationUrl()
        ]);
    }

    private function handleInvalidResponse(TiqrUserInterface $user, AuthenticationResponse $response, LoggerInterface $logger): Response
    {
        try {
            $blockedTemporarily = $this->authenticationRateLimitService->isBlockedTemporarily($user);
            $blockedPermanently = $this->authenticationRateLimitService->isBlockedPermanently($user);
            if ($blockedTemporarily || $blockedPermanently) {
                $logger->notice('User is blocked');

                return $this->showUserIsBlockedErrorPage($blockedPermanently);
            }
        } catch (Exception $e) {
            $this->logger->error('Could not determine user (temporary) block state', ['exception' => $e]);
        }

        return $this->render('default/authentication.html.twig', [
            'otpError' => true,
            'attemptsLeft' => $response instanceof RateLimitedAuthenticationResponse ? $response->getAttemptsLeft() : null,
        ]);
    }

    private function showUserIsBlockedErrorPage(bool $isBlockedPermanently): Response
    {
        $exception = new UserTemporarilyBlockedException();

        if ($isBlockedPermanently) {
            $exception = new UserPermanentlyBlockedException();
        }
        // Forward to the exception controller to prevent an error being logged.
        return $this->forward(
            'Surfnet\Tiqr\Controller\ExceptionController::show',
            [
                'exception' => $exception,
            ]
        );
    }
}
