<?php

declare(strict_types=1);

namespace Jacomeit\Belegblick;

use Jacomeit\Belegblick\Config\Configuration;
use Jacomeit\Belegblick\Lexware\LexwareClient;
use Jacomeit\Belegblick\Lexware\RequestThrottle;
use Jacomeit\Belegblick\Output\SensitiveDataFilter;
use Jacomeit\Belegblick\Tool\OpenItemsTool;
use Mcp\Schema\ToolAnnotations;
use Mcp\Server;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\HttpClient\HttpClient;

final class ServerFactory
{
    public const string NAME = 'belegblick-mcp';

    public const string VERSION = '0.1.0';

    private const string INSTRUCTIONS = 'Belegblick: unofficial, read-only access to a Lexware Office (formerly lexoffice) account. '
        . 'Not affiliated with Haufe Group. Bank details are never returned.';

    public static function create(Configuration $configuration): Server
    {
        $clock = new NativeClock('Europe/Berlin');
        $httpClient = HttpClient::createForBaseUri($configuration->baseUrl, [
            'auth_bearer' => $configuration->apiKey,
            'headers' => ['Accept' => 'application/json', 'User-Agent' => self::NAME . '/' . self::VERSION],
            'timeout' => 20,
        ]);
        $client = new LexwareClient($httpClient, new RequestThrottle($clock), $clock);
        $openItems = new OpenItemsTool($client, new SensitiveDataFilter(), $clock);

        return Server::builder()
            ->setServerInfo(self::NAME, self::VERSION)
            ->setInstructions(self::INSTRUCTIONS)
            ->addTool(
                // The SDK accepts [object, method] pairs but not invokable objects.
                [$openItems, '__invoke'],
                name: OpenItemsTool::NAME,
                title: 'Open items (unpaid sales invoices)',
                description: OpenItemsTool::DESCRIPTION,
                annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: true),
            )
            ->build();
    }
}
