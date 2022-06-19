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
use Warlof\Seat\Connector\Drivers\ISet;
use Warlof\Seat\Connector\Drivers\IUser;
use Warlof\Seat\Connector\Exceptions\DriverException;

/**
 * Class DiscourseGroup.
 *
 * @package Anza\Seat\Connector\Drivers\Discourse\Driver
 */
class DiscourseGroup implements ISet
{
    /**
     * @var string
     */
    private $id;

    /**
     * @var string
     */
    private $name;

    /**
     * @var \Warlof\Seat\Connector\Drivers\IUser[]
     */
    private $members;

    /**
     * @var \Illuminate\Support\Collection
     */
    private $builtin_groups;

    /**
     * IpbGroup constructor.
     *
     * @param array $attributes
     */
    public function __construct($attributes = [])
    {
        $this->members = collect();
        $this->hydrate($attributes);

        $this->builtin_groups = collect(config('discourse-connector.config.builtin_groups', []));
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return \Warlof\Seat\Connector\Drivers\IUser[]
     * @throws \Warlof\Seat\Connector\Exceptions\DriverException
     */
    public function getMembers(): array
    {
        if ($this->members->isEmpty()) {
            $users = DiscourseClient::getInstance()->getUsers();

            $this->members = collect(array_filter($users, function (IUser $user) {
                return in_array($this, $user->getSets());
            }));
        }

        return $this->members->toArray();
    }

    /**
     * @param \Warlof\Seat\Connector\Drivers\IUser $user
     * @throws \Warlof\Seat\Connector\Exceptions\DriverException
     */
    public function addMember(IUser $user)
    {
        if (in_array($user, $this->getMembers()))
            return;

        try {
            // add user to new group
            DiscourseClient::getInstance()->sendCall('PUT', '/groups/{group.id}/members', [
                'group.id'  => $this->id,
                'usernames' => $user->getUsername(),
            ]);
        } catch (GuzzleException $e) {
            throw new DriverException(
                sprintf('Unable to add user %s as a member of set %s.', $user->getName(), $this->getName()),
                0,
                $e);
        }

        $this->members->put($user->getClientId(), $user);
    }

    /**
     * @param \Warlof\Seat\Connector\Drivers\IUser $user
     * @throws \Warlof\Seat\Connector\Exceptions\DriverException
     */
    public function removeMember(IUser $user)
    {
        if (! in_array($user, $this->getMembers()) || $this->builtin_groups->has($this->id))
            return;

        try {
            // remove user from a group
            DiscourseClient::getInstance()->sendCall('DELETE', '/groups/{group.id}/members', [
                'group.id'  => $this->id,
                'usernames' => $user->getUsername(),
            ]);
        } catch (GuzzleException $e) {
            logger()->error(sprintf('[seat-connector][discourse] %s', $e->getMessage()));
            throw new DriverException(
                sprintf('Unable to remove user %s from set %s.', $user->getName(), $this->getName()),
                0,
                $e);
        }

        $this->members->pull($user->getClientId());
    }

    /**
     * @param array $attributes
     * @return \Anza\Seat\Connector\Drivers\Discourse\Driver\DiscourseGroup
     */
    public function hydrate(array $attributes = []): DiscourseGroup
    {
        $this->id   = $attributes['id'];
        $this->name = $attributes['name'];

        return $this;
    }
}