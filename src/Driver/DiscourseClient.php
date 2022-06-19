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

namespace Anza\Seat\Connector\Drivers\Discourse\Driver;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Arr;
use Seat\Services\Exceptions\SettingException;
use Warlof\Seat\Connector\Drivers\IClient;
use Warlof\Seat\Connector\Drivers\ISet;
use Warlof\Seat\Connector\Drivers\IUser;
use Warlof\Seat\Connector\Exceptions\DriverException;
use Warlof\Seat\Connector\Exceptions\DriverSettingsException;
use Warlof\Seat\Connector\Exceptions\InvalidDriverIdentityException;

/**
 * Class DiscourseClient.
 *
 * @package Anza\Seat\Connector\Drivers\Discourse\Driver
 */
class DiscourseClient implements IClient
{

    /**
     * @var \Anza\Seat\Connector\Drivers\Discourse\Driver\DiscourseClient
     */
    private static $instance;

    /**
     * @var \GuzzleHttp\Client
     */
    private $client;

    /**
     * @var string
     */
    private $discourse_url;

    /**
     * @var string
     */
    private $discourse_apikey;

    /**
     * @var \Warlof\Seat\Connector\Drivers\IUser[]
     */
    private $users;

    /**
     * @var \Warlof\Seat\Connector\Drivers\ISet[]
     */
    private $groups;

    /**
     * @var \Illuminate\Support\Collection
     */
    private $builtin_groups;

    /**
     * IpbClient constructor.
     *
     * @param array $parameters
     */
    private function __construct(array $parameters)
    {
        $this->discourse_url    = $parameters['discourse_url'];
        $this->discourse_apikey = $parameters['discourse_apikey'];

        $this->users   = collect();
        $this->groups  = collect();

        $this->builtin_groups = collect(config('discourse-connector.config.builtin_groups', []));

        $fetcher = config('discourse-connector.config.fetcher');
        $base_uri = rtrim($this->discourse_url, '/') . '/';
        $this->client = new $fetcher($base_uri, $this->discourse_apikey);
    }

    /**
     * @return \Anza\Seat\Connector\Drivers\Discourse\Driver\DiscourseClient
     * @throws \Warlof\Seat\Connector\Exceptions\DriverException
     */
    public static function getInstance(): IClient
    {
        if (! isset(self::$instance)) {
            try {
                $settings = setting('seat-connector.drivers.discourse', true);
            } catch (SettingException $e) {
                throw new DriverException($e->getMessage(), $e->getCode(), $e);
            }

            if (is_null($settings) || ! is_object($settings))
                throw new DriverSettingsException('The Driver has not been configured yet.');

            if (! property_exists($settings, 'discourse_url') || is_null($settings->discourse_url) || $settings->discourse_url == '')
                throw new DriverSettingsException('Parameter discourse_url is missing.');

            if (! property_exists($settings, 'discourse_apikey') || is_null($settings->discourse_apikey) || $settings->discourse_apikey == '')
                throw new DriverSettingsException('Parameter discourse_apikey is missing.');

            self::$instance = new DiscourseClient([
                'discourse_url'    => $settings->discourse_url,
                'discourse_apikey' => $settings->discourse_apikey,
            ]);
        }

        return self::$instance;
    }

    /**
     * Reset the instance
     */
    public static function tearDown()
    {
        self::$instance = null;
    }

    /**
     * @return \Warlof\Seat\Connector\Drivers\IUser[]
     * @throws \Warlof\Seat\Connector\Exceptions\DriverException
     */
    public function getUsers(): array
    {
        if ($this->users->isEmpty()) {
            try {
                $this->seedUsers();
            } catch (GuzzleException $e) {
                throw new DriverException($e->getMessage(), $e->getCode(), $e);
            }
        }

        return $this->users->toArray();
    }

    /**
     * @param string $id
     * @return \Warlof\Seat\Connector\Drivers\IUser|null
     * @throws \Warlof\Seat\Connector\Exceptions\DriverException
     */
    public function getUser(string $id): ?IUser
    {
        $user = $this->users->get($id);

        if (! is_null($user))
            return $user;

        try {
            $user = $this->sendCall('GET', '/admin/users/{id}', [
                'id' => $id,
            ]);
        } catch (ClientException $e) {
            logger()->error($e->getMessage(), $e->getTrace());

            $code = $e->getResponse()->getStatusCode();

            if (! is_null($code)) {
                switch ($code) {
                    // The user ID does not exist
                    case 404:
                        throw new InvalidDriverIdentityException(sprintf('User ID %s is not found.', $id));                       
                }
            }

            throw new DriverException($e->getMessage(), $e->getCode(), $e);
        } catch (GuzzleException $e) {
            throw new DriverException($e->getMessage(), $e->getCode(), $e);
        }

        $user = new DiscourseUser((array) $user);
        $this->users->put($user->getClientId(), $user);

        return $user;
    }

    /**
     * @return \Warlof\Seat\Connector\Drivers\ISet[]
     * @throws \Warlof\Seat\Connector\Exceptions\DriverException
     */
    public function getSets(): array
    {
        if ($this->groups->isEmpty()) {
            try {
                $this->seedGroups();
            } catch (GuzzleException $e) {
                throw new DriverException($e->getMessage(), $e->getCode(), $e);
            }
        }

        return $this->groups->toArray();
    }

    /**
     * @param string $id
     * @return \Warlof\Seat\Connector\Drivers\ISet|null
     * @throws \Warlof\Seat\Connector\Exceptions\DriverException
     */
    public function getSet(string $id): ?ISet
    {
        if ($this->groups->isEmpty()) {
            try {
                $this->seedGroups();
            } catch (GuzzleException $e) {
                throw new DriverException($e->getMessage(), $e->getCode(), $e);
            }
        }

        return $this->groups->get($id);
    }

    /**
     * @param string $method
     * @param string $endpoint
     * @param array $arguments
     * @return object
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function sendCall(string $method, string $endpoint, array $arguments = [])
    {
        $uri = ltrim($endpoint, '/');

        foreach ($arguments as $uri_parameter => $value) {
            if (strpos($uri, sprintf('{%s}', $uri_parameter)) === false)
                continue;

            $uri = str_replace(sprintf('{%s}', $uri_parameter), $value, $uri);
            Arr::pull($arguments, $uri_parameter);
        }

        if ($method == 'GET') {
            $response = $this->client->request($method, $uri, [
                'query' => $arguments,
            ]);
        } else {
            $response = $this->client->request($method, $uri, [
                'form_params' => $arguments,
            ]);
        }

        logger()->debug(
            sprintf('[seat-connector][discourse] [http %d, %s] %s -> /%s',
                $response->getStatusCode(), $response->getReasonPhrase(), $method, $uri)
        );

        return json_decode($response->getBody(), true);
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function seedUsers()
    {

        $current_page = 1;

        do {
            // set some options such as the page number we want to request from Discourse.
            $options= [
                'page'    => $current_page,
            ];

            // send the API request
            $users = $this->sendCall('GET', '/admin/users/list', $options);

            // if we have received no result, break here.
            if (empty($users))
                break;

            // iterate over our results to build our users collection.
            foreach ($users as $user_short_entry) {
                // skip system users
                if ((int) $user_short_entry['id'] < 1)
                    continue;

                // we need to get each user individually as groups aren't returned by the user list api
                $user_entry = DiscourseClient::getInstance()->sendCall('GET', '/admin/users/{user.id}.json', [
                    'user.id' => (int) $user_short_entry['id'],
                ]);

                $user = new DiscourseUser($user_entry);
                $this->users->put($user->getClientId(), $user);
            }

            // increment our current page counter
            $current_page++;
        } while (true);
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Warlof\Seat\Connector\Exceptions\DriverException
     */
    private function seedGroups()
    {

        $current_page = null;

        do {
            // set some options such as the page number we want to request from Discourse.
            $options= [
                'page'    => $current_page,
            ];

            // send the API request
            $groups = DiscourseClient::getInstance()->sendCall('GET', '/groups', $options);

            // if we have received no result, break here.
            if (empty($groups) || empty($groups['groups']))
                break;

            // iterate over our results to build our groups collection.
            foreach ($groups['groups'] as $group_entry) {
                $group = new DiscourseGroup($group_entry);
                $this->groups->put($group->getId(), $group);
            }

            // increment our current page counter
            $current_page++;
        } while (true);
    }
}