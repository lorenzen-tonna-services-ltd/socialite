<?php

/*
 * This file is part of the overtrue/socialite.
 *
 * (c) overtrue <i@overtrue.me>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Overtrue\Socialite\Providers;

use Overtrue\Socialite\AccessTokenInterface;
use Overtrue\Socialite\ProviderInterface;
use Overtrue\Socialite\User;

/**
 * Class LinkedinProvider.
 *
 * @see https://developer.linkedin.com/docs/oauth2 [Authenticating with OAuth 2.0]
 */
class LinkedinProvider extends AbstractProvider implements ProviderInterface
{
    /**
     * The scopes being requested.
     *
     * @var array
     */
    protected $scopes = ['r_basicprofile', 'r_emailaddress'];

    /**
     * The fields that are included in the profile.
     *
     * @var array
     */
    protected $fields = [
        'id', 'first-name', 'last-name', 'formatted-name', 'email-address'
    ];

    /**
     * {@inheritdoc}
     */
    protected function getAuthUrl($state)
    {
        return $this->buildAuthUrlFromBase('https://www.linkedin.com/oauth/v2/authorization', $state);
    }

    /**
     * Get the access token for the given code.
     *
     * @param string $code
     *
     * @return \Overtrue\Socialite\AccessToken
     */
    public function getAccessToken($code)
    {
        $response = $this->getHttpClient()
                         ->post($this->getTokenUrl(), ['form_params' => $this->getTokenFields($code)]);

        return $this->parseAccessToken($response->getBody());
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenUrl()
    {
        return 'https://www.linkedin.com/oauth/v2/accessToken';
    }

    /**
     * Get the POST fields for the token request.
     *
     * @param string $code
     *
     * @return array
     */
    protected function getTokenFields($code)
    {
        return parent::getTokenFields($code) + ['grant_type' => 'authorization_code'];
    }

    /**
     * {@inheritdoc}
     */
    protected function getUserByToken(AccessTokenInterface $token)
    {
        $fields = implode(',', $this->fields);

        $url = 'https://api.linkedin.com/v2/me';

        $response = $this->getHttpClient()->get($url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Restli-Protocol-Version' => '2.0.0',
                'Authorization' => 'Bearer '.$token,
            ],
        ]);

        $profile = json_decode($response->getBody(), true);

        $url = "https://api.linkedin.com/v2/emailAddress?q=members&projection=(elements*(handle~))";

        $response = $this->getHttpClient()->get($url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Restli-Protocol-Version' => '2.0.0',
                'Authorization' => 'Bearer '.$token,
            ],
        ]);

        $email = json_decode($response->getBody(), true);

        return array_merge($profile, $email);
    }

    /**
     * {@inheritdoc}
     */
    protected function mapUserToObject(array $user)
    {
        return new User([
            'id' => $user['id'],
            'nickname' => null,
            'name' => $user['firstName']['localized']['de_DE'],
            'email' => $user['elements'][0]['handle~']['emailAddress'],
            'avatar' => null,
            'avatar_original' => null,
        ]);
    }

    /**
     * Set the user fields to request from LinkedIn.
     *
     * @param array $fields
     *
     * @return $this
     */
    public function fields(array $fields)
    {
        $this->fields = $fields;

        return $this;
    }

    /**
     * Determine if the provider is operating as stateless.
     *
     * @return bool
     */
    protected function isStateless()
    {
        return true;
    }
}
