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

use Error;
use Exception;
use Surfnet\GsspBundle\Exception\UnrecoverableErrorException;
use Surfnet\StepupBundle\Controller\ExceptionController as BaseExceptionController;
use Surfnet\StepupBundle\Exception\Art;
use Surfnet\StepupBundle\Request\RequestId;
use Surfnet\Tiqr\Exception\NoActiveAuthenrequestException;
use Surfnet\Tiqr\Exception\UserNotFoundException;
use Surfnet\Tiqr\Exception\UserPermanentlyBlockedException;
use Surfnet\Tiqr\Exception\UserTemporarilyBlockedException;
use Surfnet\Tiqr\Service\ErrorPageHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
final class ExceptionController extends BaseExceptionController
{
    public function __construct(
        private readonly ErrorPageHelper $errorPageHelper,
        TranslatorInterface $translator,
        RequestId $requestId
    ) {
        parent::__construct($translator, $requestId);
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $event->setResponse($this->show($event->getRequest(), $event->getThrowable()));
    }

    public function show(Request $request, Throwable $exception): Response
    {
        $statusCode = 500;
        if ($exception instanceof Error) {
            $statusCode = $this->getStatusCode($exception);
        }

        $template = 'bundles/TwigBundle/Exception/error.html.twig';
        if ($statusCode == 404) {
            $template = 'bundles/TwigBundle/Exception/error404.html.twig';
        }

        $response = new Response('', $statusCode);

        $errorCode = Art::forException($exception);

        $params = $this->errorPageHelper->generateMetadata($request) +
            ['error_code' => $errorCode] +
            $this->getPageTitleAndDescription($exception);

        return $this->render(
            $template,
            $params,
            $response
        );
    }

    /**
     * @return array<string, string> View parameters 'title' and 'description'
     */
    protected function getPageTitleAndDescription(Throwable $exception): array
    {
        $translator = $this->getTranslator();

        if ($exception instanceof UnrecoverableErrorException && $exception->getPrevious() instanceof Throwable) {
            return $this->getPageTitleAndDescription($exception->getPrevious());
        } elseif ($exception instanceof UserNotFoundException) {
            $title = $translator->trans('login.error.user_not_found.title');
            $description = $translator->trans('login.error.user_not_found.description');
        } elseif ($exception instanceof UserTemporarilyBlockedException) {
            $title = $translator->trans('login.error.account_temporarily_blocked.title');
            $description = $translator->trans('login.error.account_temporarily_blocked.description');
        } elseif ($exception instanceof UserPermanentlyBlockedException) {
            $title = $translator->trans('login.error.account_permanently_blocked.title');
            $description = $translator->trans('login.error.account_permanently_blocked.description');
        } elseif ($exception instanceof NoActiveAuthenrequestException) {
            $title = $translator->trans('stepup.error.no_active_authentrequest.title');
            $description = $translator->trans('stepup.error.no_active_authentrequest.description');
        }

        if (isset($title) && isset($description)) {
            return [
                'title' => $title,
                'description' => $description,
            ];
        }

        return parent::getPageTitleAndDescription($exception);
    }

    /**
     * @return int HTTP status code
     */
    protected function getStatusCode(Exception|Throwable $exception): int
    {
        if ($exception instanceof UnrecoverableErrorException) {
            return Response::HTTP_NOT_ACCEPTABLE;
        }

        return parent::getStatusCode($exception);
    }
}
