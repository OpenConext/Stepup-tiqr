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

namespace Dev;

use League\Csv\Reader;
use League\Csv\Writer;
use Psr\Log\AbstractLogger;
use Symfony\Component\HttpKernel\Kernel;

final class FileLogger extends AbstractLogger
{
    private $kernel;

    public function __construct(Kernel $kernel)
    {
        $this->kernel = $kernel;
    }

    public function log($level, $message, array $context = array())
    {
        if ($level === 'debug') {
            return;
        }
        $file = fopen($this->getCSVFile(), 'ab+');
        $csv = Writer::createFromStream($file);
        $csv->setDelimiter(';');
        $csv->insertOne(array($level, $message, json_encode($context)));
        fclose($file);
    }

    public function cleanLogs()
    {
        $logs = $this->getLogs();
        $filename = $this->getCSVFile();
        if (is_file($filename)) {
            unlink($filename);
        }
        return $logs;
    }

    public function getLogs()
    {
        $filename = $this->getCSVFile();
        if (!is_file($filename)) {
            return [];
        }
        $csv = Reader::createFromStream(fopen($this->getCSVFile(), 'rb'));
        $csv->setDelimiter(';');
        $csv = $csv->fetchAll();
        return array_map(function ($line) {
            $line[2] = json_decode($line[2], true);
            return $line;
        }, $csv);
    }

    /**
     *
     * @return string
     */
    protected function getCSVFile()
    {
        $root = $this->kernel->getProjectDir();

        return implode(DIRECTORY_SEPARATOR, [$root, 'var', 'log', 'test.csv']);
    }
}
