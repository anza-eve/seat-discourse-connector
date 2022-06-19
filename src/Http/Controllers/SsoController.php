<?php
/*
 * This file is part of the ANZA Auth package for SeAT
 *
 * Copyright (C) 2018, 2019 Ben Thompson
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

namespace Anza\Seat\Connector\Drivers\Discourse\Http\Controllers;

use Anza\Seat\Connector\Drivers\Discourse\Driver\DiscourseClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Seat\Eveapi\Models\Character\CharacterInfo;
use Seat\Web\Http\Controllers\Controller;
use Warlof\Seat\Connector\Models\User;

/**
 * Class SsoController.
 *
 * Controller to process the Discourse SSO request.  There is a good bit of logic in here that almost feels like too
 * much for a controller, but given that this is the only thing that this controller is doing, I am not going to break
 * it out into some service class.
 *
 * @package Spinen\Discourse
 */
class SsoController extends Controller
{

    /**
     * @var \Seat\Web\Models\User
     */
    private $user;

     /**
     * @var Anza\Seat\Connector\Drivers\Discourse\Driver\DiscourseClient
     */
    private $client;

    /**
     * Process the SSO login request from Discourse.
     *
     * @param Request                                                          $request
     *
     * @param \Herpaderpaldent\Seat\SeatDiscourse\Action\Discourse\Groups\Sync $sync
     *
     * @return mixed
     * @throws \Cviebrock\DiscoursePHP\Exception\PayloadException
     */
    public function login(Request $request)
    {
        // Check the user has populated an email address in their profile
        if(! auth()->user()->email){
            return redirect()->route('anzaauth.profile')->with('error', 'You must enter an email address to use Discourse.');
        }

        // Get our SeAT user object
        $this->user = $request->user();
        
        // Check each of our characters has a valid refresh token.
        if($this->user->refresh_tokens->count() != $this->user->all_characters()->count()) {
            return redirect()->route('anzaauth.tokens')->with('error', 'One of your characters is missing its refresh token. Please login with the character again.');
        }

        // Validate SSO request from Discourse is valid
        if (! ($this->validatePayload($payload = $request->get('sso'), $request->get('sig')))) {
            abort(403); //Forbidden
        }
        
        // Prepare the SSO response to send back to Discourse
        $query = $this->getSignInString(
            $this->getNonce($payload),
            $this->user->id,
            $this->user->email,
            $this->buildExtraParameters()
        );

        // Redirect to Discourse with SSO response
        return redirect(Str::finish(env('DISCOURSE_URL'), '/') . 'session/sso_login?' . $query);
    }

    /**
     * Build out the extra parameters to send to Discourse.
     *
     * @return array
     */
    protected function getSsoGroups()
    {
        $this->client = DiscourseClient::getInstance();
        $sets = $this->client->getSets();
        $sso_groups = collect();

        // Generate a temporary instance of the SeAT Connector user model to get allowed sets from the SeAT Connector access mapping.
        $temp_user = new User;
        $temp_user->connector_type = 'discourse';
        $temp_user->user_id = $this->user->id;

        // Fetch the allowed sets mapping from SeAT Connector access mapping.
        $allowed_sets = $temp_user->allowedSets();

        foreach ($allowed_sets as $set_id) {
            if (array_filter($sets, function ($set) use ($set_id) {
                return $set->getId() == $set_id;
            })) {
                $sso_groups->push($sets[$set_id]->getName());
            }
        }

        return $sso_groups->implode(',');
    }

    /**
     * Build out the extra parameters to send to Discourse.
     *
     * @return array
     */
    protected function buildExtraParameters()
    {
        return [

            // Groups to make sure that the user is part of in a comma-separated string
            // NOTE: Groups cannot have spaces in their names & must already exist in Discourse
            //'add_groups' => $this->user->group->roles->map(function ($role) {return studly_case($role->title); })->implode(','),

            // Boolean for user a Discourse admin, leave null to ignore
            //'admin' => null,

            // Full path to user's avatar image
            'avatar_url' => 'http://image.eveonline.com/Character/' . $this->user->main_character_id . '_128.jpg',

            // The avatar is cached, so this triggers an update
            'avatar_force_update' => 'false',

            // Content of the user's bio
            //'bio' => null,

            // Boolean for user a Discourse admin, leave null to ignore
            //'moderator' => null,

            // Full name on Discourse if the user is new or
            // if SiteSetting.sso_overrides_name is set
            'name' => $this->user->main_character->name,

            // Groups to make sure that the user is *NOT* part of in a comma-separated string
            // NOTE: Groups cannot have spaces in their names & must already exist in Discourse
            // There is not a way to specify the exact list of groups that a user is in, so
            // you may want to send the inverse of the 'add_groups'
            //'remove_groups' => Role::all()->diff($this->user->group->roles)->map(function ($role) {return studly_case($role->title); })->implode(','),

            // If the email has not been verified, set this to true
            'require_activation' => 'false',

            // username on Discourse if the user is new or
            // if SiteSetting.sso_overrides_username is set
            'username' => $this->user->name,

            // group memberships
            'groups' => $this->getSsoGroups(),
        ];
    }

    /**
     * @param $payload
     * @param $signature
     * @return mixed
     */
    private function validatePayload($payload, $signature)
    {
        $payload = urldecode($payload);

        return $this->signPayload($payload) === $signature;
    }

    /**
     * @param $payload
     * @return mixed
     * @throws PayloadException
     */
    private function getNonce($payload)
    {
        $payload = urldecode($payload);
        $query = array();
        parse_str(base64_decode($payload), $query);
        if (!array_key_exists('nonce', $query)) {
            throw new PayloadException('Nonce not found in payload');
        }

        return $query['nonce'];
    }

    /**
     * @param $payload
     * @return mixed
     * @throws PayloadException
     */
    private function getReturnSSOURL($payload)
    {
        $payload = urldecode($payload);
        $query = array();
        parse_str(base64_decode($payload), $query);
        if (!array_key_exists('return_sso_url', $query)) {
            throw new PayloadException('Return SSO URL not found in payload');
        }

        return $query['return_sso_url'];
    }

    /**
     * @param $nonce
     * @param $id
     * @param $email
     * @param array $extraParameters
     * @return string
     */
    private function getSignInString($nonce, $id, $email, $extraParameters = [])
    {

        $parameters = array(
                'nonce'       => $nonce,
                'external_id' => $id,
                'email'       => $email,
            ) + $extraParameters;

        $payload = base64_encode(http_build_query($parameters));
        
        $data = array(
            'sso' => $payload,
            'sig' => $this->signPayload($payload),
        );

        return http_build_query($data);
    }

    /**
     * @param $payload
     * @return string
     */
    protected function signPayload($payload)
    {
        return hash_hmac('sha256', $payload, env('DISCOURSE_SSO_SECRET'));
    }
}