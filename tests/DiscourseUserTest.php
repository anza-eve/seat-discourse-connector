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
 * Class DiscourseUserTest.
 *
 * @package Anza\Seat\Connector\Drivers\Discourse\Tests
 */
class DiscourseUserTest extends TestCase
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

    public function testGetClientId()
    {
        $artifact = '1';

        $user = new DiscourseUser([
            'id' => $artifact,
            'username' => 'Mike',
            'name' => 'Test User 1',
            'groups' => [],
        ]);

        $this->assertEquals($artifact, $user->getClientId());
    }

    public function testGetUniqueId()
    {
        $artifact = 'Mike';

        $user = new DiscourseUser([
            'id' => $artifact,
            'username' => 'Mike',
            'name' => 'Test User 1',
            'groups' => [],
        ]);

        $this->assertEquals($artifact, $user->getUniqueId());
    }

    public function testGetName()
    {
        $artifact = 'Test User 1';

        $user = new DiscourseUser([
            'id' => $artifact,
            'username' => 'Mike',
            'name' => 'Test User 1',
            'groups' => [],
        ]);

        $this->assertEquals($artifact, $user->getName());
    }

    public function testGetSets()
    {
        config([
            'discourse-connector.config.mocks' => [
                new Response(200, [], file_get_contents(__DIR__ . '/artifacts/groups.json')),
            ],
        ]);

        $artifact = new DiscourseGroup([
            'id' => '41771983423143939',
            'name' => 'ANOTHER ROLE',
        ]);

        $user = new DiscourseUser([
            'nick' => 'Jeremy',
            'roles' => [
                '41771983423143939',
            ],
            'user' => [
                'id' => '9687651657897975421',
                'username' => 'Jeremy',
            ],
        ]);

        $this->assertEquals([$artifact->getId() => $artifact], $user->getSets());
    }

    public function testSetName()
    {
        config([
            'discourse-connector.config.mocks' => [
                new Response(204),
            ],
        ]);

        $artifact = 'Test User 1';

        $user = new DiscourseUser([
            'id' => $artifact,
            'username' => 'Mike',
            'name' => 'Test User 1',
            'groups' => [],
        ]);

        $user->setName('Georges');

        $this->assertNotEquals($artifact, $user->getName());
    }

    public function testSetNameGuzzleException()
    {
        config([
            'discourse-connector.config.mocks' => [
                new Response(400),
            ],
        ]);

        $user = new DiscourseUser([
            'id' => '1',
            'username' => 'Mike',
            'name' => 'Test User 1',
            'groups' => [],
        ]);

        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('Unable to change user name from Test User 1 to Georges.');

        $user->setName('Georges');
    }

    public function testAddSet()
    {
        config([
            'discourse-connector.config.mocks' => [
                new Response(204),
            ],
        ]);

        $group = new DiscourseGroup([
            'id'   => '41771983423143934',
            'name' => 'ANOTHER ROLE',
        ]);

        $user = new DiscourseUser([
            'nick'  => 'Member 5',
            'groups' => [],
            'user'  => [
                'id'       => '80351110224878913',
                'username' => 'Bob',
                'email'    => 'test@example.com',
            ],
        ]);

        $user->addSet($group);
        $this->assertEquals([$group->getId() => $group], $user->getSets());

        $user->addSet($group);
        $this->assertEquals([$group->getId() => $group], $user->getSets());
    }

    public function testAddSetGuzzleException()
    {
        config([
            'discourse-connector.config.mocks' => [
                new Response(400),
            ],
        ]);

        $group = new DiscourseGroup([
            'id'   => '41771983423143934',
            'name' => 'ANOTHER ROLE',
        ]);

        $user = new DiscourseUser([
            'nick'  => 'Member 5',
            'roles' => [],
            'user'  => [
                'id'       => '80351110224878913',
                'username' => 'Bob',
                'email'    => 'test@example.com',
            ],
        ]);

        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('Unable to add set ANOTHER ROLE to the user Member 5.');

        $user->addSet($role);
    }

    public function testRemoveSet()
    {
        config([
            'discourse-connector.config.mocks' => [
                new Response(204),
                new Response(204),
            ],
        ]);

        $role = new DiscourseGroup([
            'id'   => '41771983423143934',
            'name' => 'ANOTHER ROLE',
        ]);

        $user = new DiscourseUser([
            'nick'  => 'Member 5',
            'groups' => [],
            'user'  => [
                'id'       => '80351110224878913',
                'username' => 'Bob',
                'email'    => 'test@example.com',
            ],
        ]);

        $user->addSet($group);
        $this->assertEquals([$group->getId() => $group], $user->getSets());

        $user->removeSet($group);
        $this->assertEmpty($user->getSets());

        $user->removeSet($group);
        $this->assertEmpty($user->getSets());
    }

    public function testRemoveSetGuzzleException()
    {
        config([
            'discourse-connector.config.mocks' => [
                new Response(204),
                new Response(400),
            ],
        ]);

        $role = new DiscourseGroup([
            'id'   => '41771983423143934',
            'name' => 'ANOTHER ROLE',
        ]);

        $user = new DiscourseMMember([
            'nick'  => 'Member 5',
            'groups' => [],
            'user'  => [
                'id'       => '80351110224878913',
                'username' => 'Bob',
                'email'    => 'test@example.com',
            ],
        ]);

        $user->addSet($group);
        $this->assertEquals([$group->getId() => $group], $user->getSets());

        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('Unable to remove set ANOTHER ROLE from the user Member 5.');

        $user->removeSet($role);
    }
}
