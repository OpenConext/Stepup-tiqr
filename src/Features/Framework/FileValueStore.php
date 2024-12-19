<?php

declare(strict_types = 1);

/**
 * Copyright 2024 SURFnet B.V.
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

namespace Surfnet\Tiqr\Features\Framework;

use InvalidArgumentException;
use Surfnet\GsspBundle\Exception\NotFound;
use Surfnet\GsspBundle\Service\ValueStore;

final class FileValueStore implements ValueStore
{
    private string $filePath;

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
        if (!file_exists($this->filePath)) {
            file_put_contents($this->filePath, json_encode([], JSON_THROW_ON_ERROR));
            chmod($this->filePath, 0666);
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function readValues(): array
    {
        $content = file_get_contents($this->filePath);
        if ($content === false) {
            throw new InvalidArgumentException(sprintf('Could not read FileValueStore storage file. %s', $this->filePath));
        }

        $result = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        return is_array($result) ? $result : [];
    }

    /**
     * @param array<string,mixed> $values
     */
    private function writeValues(array $values): void
    {
        file_put_contents($this->filePath, json_encode($values, JSON_THROW_ON_ERROR));
    }

    public function set(string $key, mixed $value): self
    {
        $values = $this->readValues();
        $values[$key] = $value;
        $this->writeValues($values);
        return $this;
    }

    public function get(string $key): mixed
    {
        $values = $this->readValues();
        if (!isset($values[$key])) {
            throw NotFound::stateProperty($key);
        }
        return $values[$key];
    }

    /**
     * @SuppressWarnings(PHPMD.ShortMethodName)
    */
    public function is(string $key, mixed $value): bool
    {
        $values = $this->readValues();
        return isset($values[$key]) && $values[$key] === $value;
    }

    public function has(string $key): bool
    {
        $values = $this->readValues();
        return isset($values[$key]);
    }

    public function clear(): self
    {
        $this->writeValues([]);
        return $this;
    }
}
