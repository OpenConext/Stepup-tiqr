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

namespace App\Features\Context;

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Hook\Scope\StepScope;
use Behat\Mink\Driver\Selenium2Driver;
use Behat\Mink\Exception\DriverException;
use Behat\MinkExtension\Context\MinkContext;
use Behat\Symfony2Extension\Context\KernelAwareContext;
use Behat\Testwork\Tester\Result\TestResult;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Generates a HTML/png error output report when a build fails.
 */
final class ErrorReportContext implements Context
{

    /**
     * @var MinkContext
     */
    private $minkContext;
    private ?KernelInterface $kernel = null;

    /**
     * Fetch the required contexts.
     *
     *
     * @BeforeScenario
     */
    public function gatherContexts(BeforeScenarioScope $scope): void
    {

        $environment = $scope->getEnvironment();
        $this->minkContext = $environment->getContext(MinkContext::class);
    }

    /**
     * This will print the failed html result.
     *
     *
     * @AfterStep
     */
    public function dumpInfoAfterFailedStep(AfterStepScope $scope): void
    {
        if ($this->stepIsSuccessful($scope)) {
            return;
        }
        try {
            $scenario = $this->getScenario($scope);
            if (null !== $scenario) {
                $title = $scenario->getTitle();
            }
            if (null === $scenario) {
                $step = $this->getBackGroundStep($scope);
                $title = $step->getNodeType().'-'.$step->getText();
            }
            $filename = preg_replace('/[^a-zA-Z0-9]/', '-', $title);

            $this->saveErrorFile($scope, $filename);
            $this->takeScreenShotAfterFailedStep($filename);
        } catch (DriverException) {
            return;
        }
    }

    /**
     * Saves screen shot.
     *
     * @param string $fileName
     *
     * @throws \Behat\Mink\Exception\DriverException
     */
    private function takeScreenShotAfterFailedStep(string|array|null $fileName): void
    {
        $session = $this->minkContext->getSession();
        if (!($session->getDriver() instanceof Selenium2Driver)) {
            return;
        }
        $session->resizeWindow(1440, 900, 'current');
        $path = sprintf('%s/%s.png', $this->getOutputPath(), $fileName);
        file_put_contents($path, $session
            ->getDriver()
            ->getScreenshot());

        print "Screenshots saved: $path";
    }

    /**
     * Save the page result file to disk.
     *
     * @param string $fileName
     */
    private function saveErrorFile(AfterStepScope $scope, string|array|null $fileName): void
    {
        $session = $this->minkContext->getSession();
        $content = <<< TEXT
feature: {$scope->getFeature()->getTitle()}
step: {$scope->getStep()->getText()}
url: {$session->getCurrentUrl()}
{$this->minkContext->getSession()->getPage()->getContent()}
TEXT;
        $path = sprintf('%s/%s.html', $this->getOutputPath(), $fileName);
        file_put_contents($path, $content);
        print "Page content printed to: $path";
    }

    /**
     * Check if test is successful.
     *
     * @param \Behat\Behat\Hook\Scope\AfterStepScope $scope
     *   The test scope.
     *
     * @return bool
     *   TRUE if it is successfully.
     */
    private function stepIsSuccessful(AfterStepScope $scope): bool
    {
        return $scope->getTestResult()->getResultCode() !== TestResult::FAILED;
    }

    /**
     * Returns the scenaro for a given step.
     *
     *
     * @return \Behat\Gherkin\Node\ScenarioInterface
     */
    private function getScenario(StepScope $scope)
    {
        $scenario = null;
        $feature = $scope->getFeature();
        $step = $scope->getStep();
        $line = $step->getLine();
        foreach ($feature->getScenarios() as $tmp) {
            if ($tmp->getLine() > $line) {
                break;
            }
            $scenario = $tmp;
        }

        return $scenario;
    }

    /**
     * Returns the scenario for a given step.
     *
     *
     * @return \Behat\Gherkin\Node\NodeInterface|null
     */
    private function getBackGroundStep(StepScope $scope)
    {
        $feature = $scope->getFeature();
        $step = $scope->getStep();
        $line = $step->getLine();
        foreach ($feature->getBackground()->getSteps() as $tmp) {
            if ($tmp->getLine() === $line) {
                return $tmp;
            }
        }

        return null;
    }

    private function getOutputPath(): string
    {
        $root = $this->kernel->getProjectDir();
        return implode(DIRECTORY_SEPARATOR, [$root, 'var', 'log']);
    }

    /**
     * Sets Kernel instance.
     */
    public function setKernel(KernelInterface $kernel): void
    {
        $this->kernel = $kernel;
    }
}
