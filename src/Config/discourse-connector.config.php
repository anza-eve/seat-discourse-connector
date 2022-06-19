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
    'fetcher'        => \Anza\Seat\Connector\Drivers\Discourse\Fetchers\GuzzleFetcher::class,
    'version'        => '1.0.0',
    'builtin_groups' => [
        1 => 'admins', 
        2 => 'moderators', 
        3 => 'staff', 
        10 => 'trust_level_0',
        11 => 'trust_level_1',
        12 => 'trust_level_2',
        13 => 'trust_level_3',
        14 => 'trust_level_4',
    ],
];
