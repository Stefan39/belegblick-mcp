<?php

declare(strict_types=1);

namespace Jacomeit\LexwarePhpMcp\Exception;

final class MissingApiKeyException extends \RuntimeException
{
    public static function create(): self
    {
        return new self('LEXWARE_API_KEY is not set. Create a key at https://app.lexware.de/addons/public-api and pass it via the environment.');
    }
}
