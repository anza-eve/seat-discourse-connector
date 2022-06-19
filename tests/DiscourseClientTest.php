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
use Anza\Seat\Connector\Drivers\Discourse\Driver\DiscourseUser;
use Anza\Seat\Connector\Drivers\Discourse\Driver\DiscourseGroup;
use Anza\Seat\Connector\Drivers\Discourse\Tests\Fetchers\TestFetcher;
use GuzzleHttp\Psr7\Response;
use Orchestra\Testbench\TestCase;

/**
 * Class DiscourseClientTest.
 *
 * @package Warlof\Seat\Connector\Drivers\Discourse\Tests
 */
class DiscourseClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/migrations');
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     */
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

    public function testGetAllUsers()
    {
        $settings = (object) [
            'discourse_url' => 'https://discourse.example.com/',
            'discourse_apikey' => 'abcde-4fs8s7f51sq654g',
        ];

        setting(['seat-connector.drivers.discourse', $settings], true);

        config([
            'discourse-connector.config.mocks' => [
                new Response(200, [], file_get_contents(__DIR__ . '/artifacts/users.json')),
                new Response(200, [], '[]'),
            ],
        ]);

        $driver = DiscourseClient::getInstance();

        $artifact = [
            '10' => new DiscourseUser([
                'id' => '10',
                'username' => 'Nelly',
                'name' => 'Member 1',
                'groups' => [],
            ]),
            '11' => new DiscourseUser([
                'id' => '11',
                'username' => 'Mike',
                'name' => 'Member 2',
                'groups' => [],
            ]),
            '12' => new DiscourseUser([
                'id' => '12',
                'username' => 'Clarke',
                'name' => 'Member 3',
                'groups' => [],
            ]),
        ];

        $this->assertEquals($artifact, $driver->getUsers());
    }

    public function testGetExistingSingleUser()
    {
        $settings = (object) [
            'discourse_url' => 'https://discourse.example.com/',
            'discourse_apikey' => 'abcde-4fs8s7f51sq654g',
        ];

        setting(['seat-connector.drivers.discourse', $settings], true);

        config([
            'discourse-connector.config.mocks' => [
                new Response(200, [], file_get_contents(__DIR__ . '/artifacts/user.json')),
                new Response(200, [], '[]'),
            ],
        ]);

        $user_id = '11';
        $artifact = new DiscourseUser([
            'id' => '12',
                'username' => 'Mike',
                'name' => 'Member 2',
                'groups' => [],
        ]);

        $driver = DiscourseClient::getInstance();
        $driver->getUsers();

        $this->assertEquals($artifact, $driver->getUser($user_id));
    }

    public function testGetMissingSingleUser()
    {
        $settings = (object) [
            'discourse_url' => 'https://discourse.example.com/',
            'discourse_apikey' => 'abcde-4fs8s7f51sq654g',
        ];

        setting(['seat-connector.drivers.discourse', $settings], true);

        config([
            'discourse-connector.config.mocks' => [
                new Response(200, [], file_get_contents(__DIR__ . '/artifacts/user.json')),
            ],
        ]);

        $user_id = '24';
        $artifact = new DiscourseMember([
            'id' => $user_id,
            'username' => 'Jocelyn',
            'name' => 'Member 4',
            'groups' => [],
        ]);

        $driver = DiscourseClient::getInstance();

        $this->assertEquals($artifact, $driver->getUser($user_id));
    }

    public function testGetAllSets()
    {
        $settings = (object) [
            'discourse_url' => 'https://discourse.example.com/',
            'discourse_apikey' => 'abcde-4fs8s7f51sq654g',
        ];

        setting(['seat-connector.drivers.discourse', $settings], true);

        config([
            'discourse-connector.config.mocks' => [
                new Response(200, [], file_get_contents(__DIR__ . '/artifacts/groups.json')),
            ],
        ]);

        $artifact = [
            10 => new DiscourseGroup([
                'id'   => '50',
                'name' => 'TEST GROUP',
            ]),
            11 => new DiscourseGroup([
                'id'   => '51',
                'name' => 'Another Test Group',
            ]),
            12 => new DiscourseGroup([
                'id'   => '52',
                'name' => 'Yet another test group',
            ]),
        ];

        $driver = DiscourseClient::getInstance();

        $this->assertEquals($artifact, $driver->getSets());
    }

    public function testGetSingleSet()
    {
        $settings = (object) [
            'discourse_url' => 'https://discourse.example.com/',
            'discourse_apikey' => 'abcde-4fs8s7f51sq654g',
        ];

        setting(['seat-connector.drivers.discourse', $settings], true);

        config([
            'discourse-connector.config.mocks' => [
                new Response(200, [], file_get_contents(__DIR__ . '/artifacts/groups.json')),
            ],
        ]);

        $set_id = '50';
        $artifact = new DiscourseGroup([
            'id'   => $set_id,
            'name' => 'TEST GROUP',
        ]);

        $driver = DiscourseClient::getInstance();

        $this->assertEquals($artifact, $driver->getSet($set_id));
    }
}
