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

namespace Anza\Seat\Connector\Drivers\Discourse\Tests;

use Anza\Seat\Connector\Drivers\Discourse\Driver\DiscourseClient;
use Anza\Seat\Connector\Drivers\Discourse\Tests\Fetchers\TestFetcher;
use GuzzleHttp\Psr7\Response;
use Orchestra\Testbench\TestCase;
use Warlof\Seat\Connector\Exceptions\DriverException;
use Warlof\Seat\Connector\Exceptions\DriverSettingsException;
use Warlof\Seat\Connector\Exceptions\InvalidDriverIdentityException;

/**
 * Class Test.
 *
 * @package Anza\Seat\Connector\Drivers\Discourse\Tests
 */
class DriverExceptionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/migrations');
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('discourse-connector.config.fetcher', TestFetcher::class);
        $app['config']->set('discourse-connector.config.version', 'dev');
    }

    protected function tearDown(): void
    {
        DiscourseClient::tearDown();

        parent::tearDown();
    }

    public function testUnsetSettingsDriverException()
    {
        $this->expectException(DriverSettingsException::class);
        $this->expectExceptionMessage('The Driver has not been configured yet.');

        DiscourseClient::getInstance();
    }

    public function testDiscourseUrlDriverSettingException()
    {
        setting([
            'seat-connector.drivers.discourse', (object) [
                'discourse_url' => null,
            ],
        ], true);

        $this->expectException(DriverSettingsException::class);
        $this->expectExceptionMessage('Parameter discourse_url is missing.');

        DiscourseClient::getInstance();
    }

    public function testApiKeyDriverSettingException()
    {
        setting([
            'seat-connector.drivers.discourse', (object) [
                'discourse_apikey' => null,
            ],
        ], true);

        $this->expectException(DriverSettingsException::class);
        $this->expectExceptionMessage('Parameter discourse_apikey is missing.');

        DiscourseClient::getInstance();
    }

    public function testGetSetsException()
    {
        $settings = (object) [
            'discourse_url' => 'https://discourse.example.com/',
            'discourse_apikey' => 'abcde-4fs8s7f51sq654g',
        ];

        setting(['seat-connector.drivers.discourse', $settings], true);

        config([
            'discourse-connector.config.mocks' => [
                new Response(403),
            ],
        ]);

        $this->expectException(DriverException::class);

        DiscourseClient::getInstance()->getSets();
    }

    public function testGetSingleSetException()
    {
        $settings = (object) [
            'discourse_url' => 'https://discourseexample.com/',
            'discourse_apikey' => 'abcde-4fs8s7f51sq654g',
        ];

        setting(['seat-connector.drivers.discourse', $settings], true);

        config([
            'discourse-connector.config.mocks' => [
                new Response(404),
            ],
        ]);

        $this->expectException(DriverException::class);

        DiscourseClient::getInstance()->getSet('10');
    }

    public function testGetUsersGuzzleException()
    {
        $settings = (object) [
            'discourse_url' => 'https://discourse.example.com/',
            'discourse_apikey' => 'abcde-4fs8s7f51sq654g',
        ];

        setting(['seat-connector.drivers.discourse', $settings], true);

        config([
            'discourse-connector.config.mocks' => [
                new Response(400),
            ],
        ]);

        $this->expectException(DriverException::class);

        DiscourseClient::getInstance()->getUsers();
    }

    public function testGetSingleUserDriverSettingsException()
    {
        $settings = (object) [
            'discourse_url' => 'https://discourse.example.com/',
            'discourse_apikey' => 'abcde-4fs8s7f51sq654g',
        ];

        setting(['seat-connector.drivers.discourse', $settings], true);

        config([
            'discourse-connector.config.mocks' => [
                new Response(404),
            ],
        ]);

        $this->expectException(DriverSettingsException::class);
        $this->expectExceptionMessage('Configured discourse_url is invalid.');

        DiscourseClient::getInstance()->getUser('3');
    }

    public function testGetSingleUserInvalidDriverIdentityException()
    {
        $settings = (object) [
            'discourse_url' => 'https://discourse.example.com/',
            'discourse_apikey' => 'abcde-4fs8s7f51sq654g',
        ];

        setting(['seat-connector.drivers.discourse', $settings], true);

        config([
            'discourse-connector.config.mocks' => [
                new Response(404, [], '{"errorCode": "1C292\/2", "errorMessage": "INVALID_ID"}'),
            ],
        ]);

        $this->expectException(InvalidDriverIdentityException::class);
        $this->expectExceptionMessage('User ID 3 is not found.');

        DiscourseClient::getInstance()->getUser('3');
    }

    public function testGetSingleUserClientException()
    {
        $settings = (object) [
            'discourse_url' => 'https://discourse.example.com/',
            'discourse_apikey' => 'abcde-4fs8s7f51sq654g',
        ];

        setting(['seat-connector.drivers.discourse', $settings], true);

        config([
            'discourse-connector.config.mocks' => [
                new Response(403),
            ],
        ]);

        $this->expectException(DriverException::class);

        DiscourseClient::getInstance()->getUser('3');
    }

    public function testGetSingleUserGuzzleException()
    {
        $settings = (object) [
            'discourse_url' => 'https://discourse.example.com/',
            'discourse_apikey' => 'abcde-4fs8s7f51sq654g',
        ];

        setting(['seat-connector.drivers.discourse', $settings], true);

        config([
            'discourse-connector.config.mocks' => [
                new Response(500),
            ],
        ]);

        $this->expectException(DriverException::class);

        DiscourseClient::getInstance()->getUser('3');
    }
}
