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
use Anza\Seat\Connector\Drivers\Discourse\Driver\DiscourseGroup;
use Anza\Seat\Connector\Drivers\Discourse\Driver\DiscourseUser;
use Anza\Seat\Connector\Drivers\Discourse\Tests\Fetchers\TestFetcher;
use GuzzleHttp\Psr7\Response;
use Orchestra\Testbench\TestCase;
use Warlof\Seat\Connector\Exceptions\DriverException;

/**
 * Class DiscourseGroupTest.
 *
 * @package Warlof\Seat\Connector\Drivers\Discourse\Tests
 */
class DiscourseGroupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/migrations');

        $settings = (object) [
            'discourse_url' => 'https://discourse.example.com/',
            'discourse_apikey' => 'abcde-4fs8s7f51sq654g',
        ];

        setting(['seat-connector.drivers.discourse', $settings], true);
    }

    protected function tearDown(): void
    {
        DiscourseClient::tearDown();

        parent::tearDown();
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

    public function testGetId()
    {
        $artifact = '50';

        $set = new DiscourseGroup([
            'id'   => $artifact,
            'name' => 'TEST GROUP',
        ]);

        $this->assertEquals($artifact, $set->getId());
    }

    public function testGetName()
    {
        $artifact = 'TEST GROUP';

        $set = new DiscourseGroup([
            'id'   => '50',
            'name' => $artifact,
        ]);

        $this->assertEquals($artifact, $set->getName());
    }

    public function testAddMember()
    {
        config([
            'discourse-connector.config.mocks' => [
                new Response(200, [], file_get_contents(__DIR__ . '/artifacts/users.json')),
                new Response(200, [], '[]'),
                new Response(204),
            ],
        ]);

        $group = new DiscourseGroup([
            'id'   => '51',
            'name' => 'Another Test Group',
        ]);

        $user = new DiscourseUser([
            'id' => '1',
            'username' => 'Mike',
            'name' => 'Test User 1',
            'groups' => [],
        ]);

        $group->addMember($user);
        $this->assertEquals([$user->getClientId() => $user], $group->getMembers());

        $group->addMember($user);
        $this->assertEquals([$user->getClientId() => $user], $group->getMembers());
    }

    public function testAddMemberGuzzleException()
    {
        config([
            'discourse-connector.config.mocks' => [
                new Response(200, [], file_get_contents(__DIR__ . '/artifacts/users.json')),
                new Response(200, [], '[]'),
                new Response(400),
            ],
        ]);

        $group = new DiscourseGroup([
            'id'   => '51',
            'name' => 'Another Test Group',
        ]);

        $user = new DiscourseUser([
            'id' => '1',
            'username' => 'Mike',
            'name' => 'Test User 1',
            'groups' => [],
        ]);

        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('Unable to add user Member 2 as a member of set TEST ROLE.');

        $role->addMember($user);
    }

    public function testRemoveMember()
    {
        config([
            'discourse-connector.config.mocks' => [
                new Response(200, [], file_get_contents(__DIR__ . '/artifacts/users.json')),
                new Response(200, [], '[]'),
                new Response(204),
                new Response(204),
            ],
        ]);

        $group = new DiscourseGroup([
            'id'   => '11',
            'name' => 'Another Test Group',
        ]);

        $user = new DiscourseUser([
            'id' => '1',
            'username' => 'Mike',
            'name' => 'Test User 1',
            'groups' => [],
        ]);

        $role->addMember($user);
        $this->assertEquals([$user->getClientId() => $user], $group->getMembers());

        $role->removeMember($user);
        $this->assertEmpty($role->getMembers());

        $role->removeMember($user);
        $this->assertEmpty($role->getMembers());
    }

    public function testRemoveMemberGuzzleException()
    {
        config([
            'discourse-connector.config.mocks' => [
                new Response(200, [], file_get_contents(__DIR__ . '/artifacts/members.json')),
                new Response(200, [], '[]'),
                new Response(204),
                new Response(400),
            ],
        ]);

        $group = new DiscourseGroup([
            'id'   => '50',
            'name' => 'TEST ROLE',
        ]);

        $user = new DiscourseUser([
            'id' => '1',
            'username' => 'Mike',
            'name' => 'Test User 1',
            'groups' => [],
        ]);

        $role->addMember($user);
        $this->assertEquals([$user->getClientId() => $user], $group->getMembers());

        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('Unable to remove user Member 2 from set TEST ROLE.');

        $role->removeMember($user);
    }
}
