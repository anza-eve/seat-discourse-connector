<?php
/**
 * This file is part of SeAT Discourse Connector.
 *
 * Copyright (C) 2021  Ben Thompson <ben.thompson002@gmail.com>
 *
 * SeAT Discourse Connector is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * SeAT Discourse Connector is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace Anza\Seat\Connector\Drivers\Discourse\Tests\Fetchers;

use Anza\Seat\Connector\Drivers\Discourse\Fetchers\IFetcher;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use Psr\Http\Message\ResponseInterface;

/**
 * Class TestFetcher.
 *
 * @package Anza\Seat\Connector\Drivers\Discourse\Tests\Fetchers
 */
class TestFetcher implements IFetcher
{
    /**
     * @var \GuzzleHttp\Handler\MockHandler
     */
    public $handler;

    /**
     * @var \GuzzleHttp\Client
     */
    private $client;

    /**
     * @inheritDoc
     */
    public function __construct(string $base_uri, string $token)
    {
        $this->handler = MockHandler::createWithMiddleware(config('discourse-connector.config.mocks', []));

        $this->client = new Client([
            'base_uri' => $base_uri,
            'headers'  => [
                'Authorization' => $token,
                'Content-Type'  => 'application/json',
                'User-Agent'    => sprintf('anza-eve@seat-discourse-connector/%s GitHub SeAT', config('discourse-connector.config.version')),
            ],
            'handler' => $this->handler,
        ]);
    }

    /**
     * @inheritDoc
     */
    public function request(string $method, string $uri = '', array $options = []): ResponseInterface
    {
        return $this->client->request($method, $uri, $options);
    }
}
