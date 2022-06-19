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

return [
    'name'        => 'discourse',
    'icon'        => 'fa fa-comment',
    'client'      => \Anza\Seat\Connector\Drivers\Discourse\Driver\DiscourseClient::class,
    'settings'    => [
        [
            'name'  => 'discourse_url',
            'label' => 'seat-connector-discourse::seat.discourse_url',
            'type'  => 'text',
        ],
        [
            'name'  => 'discourse_apikey',
            'label' => 'seat-connector-discourse::seat.discourse_apikey',
            'type'  => 'text',
        ],
    ],
];
