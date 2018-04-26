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

namespace AppBundle\Controller;

use AppBundle\Exception\UserNotFoundException;
use AppBundle\Exception\UserPermanentlyBlockedException;
use AppBundle\Exception\UserTemporarilyBlockedException;
use Exception;
use Surfnet\GsspBundle\Exception\UnrecoverableErrorException;
use Surfnet\StepupBundle\Controller\ExceptionController as BaseExceptionController;
use Symfony\Component\HttpFoundation\Response;

final class ExceptionController extends BaseExceptionController
{
    /**
     * @param Exception $exception
     * @return array View parameters 'title' and 'description'
     */
    protected function getPageTitleAndDescription(Exception $exception)
    {
        $translator = $this->getTranslator();

        if ($exception instanceof UserNotFoundException) {
            $title = $translator->trans('login.error.user_not_found.title');
            $description = $translator->trans('login.error.user_not_found.description');
        } elseif ($exception instanceof UserTemporarilyBlockedException) {
            $title = $translator->trans('login.error.account_temporarily_blocked.title');
            $description = $translator->trans('login.error.account_temporarily_blocked.description');
        } elseif ($exception instanceof UserPermanentlyBlockedException) {
            $title = $translator->trans('login.error.account_permanently_blocked.description.title');
            $description = $translator->trans('login.error.account_permanently_blocked.description');
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
     * @param Exception $exception
     * @return int HTTP status code
     */
    protected function getStatusCode(Exception $exception)
    {
        if ($exception instanceof UnrecoverableErrorException) {
            return Response::HTTP_NOT_ACCEPTABLE;
        }

        return parent::getStatusCode($exception);
    }
}
