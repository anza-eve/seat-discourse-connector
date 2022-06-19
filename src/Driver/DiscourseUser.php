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

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Str;
use Seat\Services\Exceptions\SettingException;
use Warlof\Seat\Connector\Drivers\ISet;
use Warlof\Seat\Connector\Drivers\IUser;
use Warlof\Seat\Connector\Exceptions\DriverException;

/**
 * Class DiscourseUser.
 *
 * @package Anza\Seat\Connector\Drivers\Discourse\Driver
 */
class DiscourseUser implements IUser
{
    /**
     * @var string
     */
    private $id;

    /**
     * @var string
     */
    private $username;

    /**
     * @var string
     */
    private $name;

    /**
     * @var string[]
     */
    private $group_ids;

    /**
     * @var \Warlof\Seat\Connector\Drivers\ISet[]
     */
    private $groups;

    /**
     * @var \Illuminate\Support\Collection
     */
    private $builtin_groups;

    /**
     * DiscourseUser constructor.
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->group_ids = [];
        $this->groups    = collect();
        $this->hydrate($attributes);

        $this->builtin_groups = collect(config('discourse-connector.config.builtin_groups', []));
    }

    /**
     * @return string
     */
    public function getClientId(): string
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getUniqueId(): string
    {
        return $this->username;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return bool
     * @throws \Warlof\Seat\Connector\Exceptions\DriverException
     */
    public function setName(string $name): bool
    {

        try {
            DiscourseClient::getInstance()->sendCall('PUT', '/u/{user.username}/preferences/username', [
                'user.username' => $this->getUsername(),
                'new_username'  => $name,
            ]);
        } catch (GuzzleException $e) {
            logger()->error(sprintf('[seat-connector][discourse] %s', $e->getMessage()));
            throw new DriverException(
                sprintf('Unable to change user name from %s to %s.', $this->getName(), $name),
                0,
                $e);
        }

        $this->username = $name;

        return true;
    }

    /**
     * @return \Warlof\Seat\Connector\Drivers\ISet[]
     * @throws \Warlof\Seat\Connector\Exceptions\DriverException
     */
    public function getSets(): array
    {
        if ($this->groups->isEmpty()) {
            foreach ($this->group_ids as $group_id) {
                $set = DiscourseClient::getInstance()->getSet($group_id);

                if (is_null($set)) continue;

                $this->groups->put($group_id, $set);
            }
        }

        return $this->groups->toArray();
    }

    /**
     * @param array $attributes
     * @return \Warlof\Seat\Connector\Drivers\Discourse\Driver\DiscourseUser
     */
    public function hydrate(array $attributes = []): DiscourseUser
    {
        $this->id       = $attributes['id'];
        $this->username = $attributes['username'];
        $this->name     = $attributes['name'];
        
        $group_ids = collect($attributes['groups']);
        $this->group_ids = $group_ids->pluck('id')->unique()->flatten()->all();

        return $this;
    }

    /**
     * @param \Warlof\Seat\Connector\Drivers\ISet $group
     * @throws \Warlof\Seat\Connector\Exceptions\DriverException
     */
    public function addSet(ISet $group)
    {
        try {
            if (in_array($group->getId(), $this->group_ids))
                return;

            // add user to a group
            DiscourseClient::getInstance()->sendCall('PUT', '/groups/{group.id}/members', [
                'group.id'  => $group->getId(),
                'usernames' => $this->username,
            ]);

            $this->group_ids[] = $group->getId();
            $this->groups->put($group->getId(), $group);
        } catch (SettingException | GuzzleException $e) {
            throw new DriverException(
                sprintf('Unable to add set %s to the user %s.', $group->getName(), $this->getName()),
                0,
                $e);
        }
    }

    /**
     * @param \Warlof\Seat\Connector\Drivers\ISet $group
     * @throws \Warlof\Seat\Connector\Exceptions\DriverException
     */
    public function removeSet(ISet $group)
    {
        try {
            if (! in_array($group->getId(), $this->group_ids) || $this->builtin_groups->has($group->getId()))
                return;

            // remove user from a group
            DiscourseClient::getInstance()->sendCall('DELETE', '/groups/{group.id}/members', [
                'group.id'  => $group->getId(),
                'usernames' => $this->username,
            ]);

            $this->groups->pull($group->getId());

            $key = array_search($group->getId(), $this->group_ids);

            if ($key !== false) {
                unset($this->group_ids[$key]);
            }
        } catch (SettingException | GuzzleException $e) {
            logger()->error(sprintf('[seat-connector][discourse] %s', $e->getMessage()));
            throw new DriverException(
                sprintf('Unable to remove set %s from the user %s.', $group->getName(), $this->getName()),
                0,
                $e);
        }
    }
}