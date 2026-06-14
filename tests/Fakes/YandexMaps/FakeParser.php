<?php

namespace Tests\Fakes\YandexMaps;

use App\DTO\YandexMaps\OrganizationDto;
use App\Services\YandexMaps\Parser;
use LogicException;
use Throwable;

final class FakeParser implements Parser
{
    private ?OrganizationDto $organization = null;

    private ?Throwable $exception = null;

    /**
     * Configures the organization DTO returned by parse()
     */
    public function returns(OrganizationDto $organization): self
    {
        $this->organization = $organization;
        $this->exception = null;

        return $this;
    }

    /**
     * Configures parse() to throw the given exception
     */
    public function throws(Throwable $exception): self
    {
        $this->exception = $exception;
        $this->organization = null;

        return $this;
    }

    public function parse(string $url): OrganizationDto
    {
        if ($this->exception !== null) {
            throw $this->exception;
        }

        if ($this->organization === null) {
            throw new LogicException('FakeParser result is not configured.');
        }

        return $this->organization;
    }
}
