<?php

/** @noinspection PhpUndefinedClassInspection */

namespace Officio\Service;

use Laminas\Db\Sql\Select;
use League\OAuth2\Client\Provider\Google;
use Officio\Common\Service\BaseService;
use Stevenmaguire\OAuth2\Client\Provider\Keycloak;
use Stevenmaguire\OAuth2\Client\Provider\Microsoft;

class OAuth2Client extends BaseService
{


    /**
     * Get oAuth provider
     *
     * @param ?string $provider
     * @param string $type
     * @return Keycloak|Microsoft|Google|null
     */
    public function getOAuthProvider($provider = null, $type = 'login')
    {
        $oAuthSettings = $this->_config['security']['oauth_login'];

        $provider = empty($provider) ? $oAuthSettings['provider'] : $provider;
        if ($type == 'login') {
            $redirectUrl = $this->_config['urlSettings']['baseUrl'] . '/auth/oauth-callback';
        } else {
            $redirectUrl = $this->_config['urlSettings']['baseUrl'] . '/mailer/settings/oauth-callback';
        }
        switch ($provider) {
            case 'keycloak':
                $arrSettings = [
                    'authServerUrl' => $oAuthSettings['keycloak']['server-url'],
                    'realm'         => $oAuthSettings['keycloak']['realm'],
                    'clientId'      => $oAuthSettings['keycloak']['client-id'],
                    'clientSecret'  => $oAuthSettings['keycloak']['client-secret'],
                    'redirectUri'   => $redirectUrl,
                ];

                $provider = new Keycloak($arrSettings);
                break;

            case 'google':
                $arrSettings = [
                    'clientId'     => $oAuthSettings['google']['client-id'],
                    'clientSecret' => $oAuthSettings['google']['client-secret'],
                    'redirectUri'  => $redirectUrl,
                ];

                $provider = new Google($arrSettings);
                break;

            case 'microsoft':
                $arrSettings = [
                    'clientId'       => $oAuthSettings['microsoft']['client-id'],
                    'clientSecret'   => $oAuthSettings['microsoft']['client-secret'],
                    'redirectUri'    => $redirectUrl,
                    'urlAuthorize'   => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
                    'urlAccessToken' => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
                ];

                $provider = new Microsoft($arrSettings);
                break;

            default:
                $provider = null;
                break;
        }

        return $provider;
    }

    /**
     * Get a supported provider by the host url
     *
     * @param string $host
     * @return string
     */
    public function getOAuthProviderByHost($host)
    {
        switch ($host) {
            case 'outlook.office365.com':
            case 'smtp.office365.com':
            case 'imap-mail.outlook.com':
            case 'pop-mail.outlook.com':
            case 'smtp-mail.outlook.com':
                $provider = 'microsoft';
                break;

            case 'imap.gmail.com':
            case 'smtp.gmail.com':
                // TODO: uncomment when Google oAuth will be supported
                // $provider = 'google';
                $provider = '';
                break;

            default:
                $provider = '';
                break;
        }

        return $provider;
    }

    /**
     * Get scopes params required for oAuth authorization
     *
     * @param string $provider microsoft or google
     * @param string $type pop3 or imap or smtp
     * @return array
     */
    public function getOAuthProviderScopesByProvider($provider, $type)
    {
        switch ($provider) {
            case 'microsoft':
                switch ($type) {
                    case 'pop3':
                        $arrScopes = [
                            'scope' => ['https://outlook.office.com/POP.AccessAsUser.All offline_access']
                        ];
                        break;

                    case 'imap':
                        $arrScopes = [
                            'scope' => ['https://outlook.office.com/IMAP.AccessAsUser.All offline_access']
                        ];
                        break;

                    case 'smtp':
                    default:
                        $arrScopes = [
                            'scope' => ['https://outlook.office.com/SMTP.Send offline_access']
                        ];
                        break;
                }
                break;

            case 'google':
            default:
                $arrScopes = [];
                break;
        }

        return $arrScopes;
    }

    /**
     * Get access token for a specific member/host/email
     *
     * @param int $memberId
     * @param string $host
     * @param string $email
     * @param string $type
     * @return array
     */
    public function getAccessToken($memberId, $host, $email, $type)
    {
        $strError    = '';
        $accessToken = '';

        try {
            $provider = $this->getOAuthProviderByHost($host);
            if (empty($provider)) {
                $strError = $this->_tr->translate('This host is not supported.');
            }

            if (empty($strError)) {
                $select = (new Select())
                    ->from('acl_remote_auth')
                    ->where([
                        'member_id'         => $memberId,
                        'provider'          => $provider,
                        'remote_account_id' => $email,
                        'additional_data'   => $type,
                    ]);

                $oAuthSettings = $this->_db2->fetchRow($select);

                $accessToken = !empty($oAuthSettings['access_token']) ? $oAuthSettings['access_token'] : '';

                // Check if this access token is valid
                if (!empty($accessToken)) {
                    $oProvider = $this->getOAuthProvider($provider, 'mail');
                    if (!is_null($oProvider)) {
                        try {
                            $accessToken = $oProvider->getAccessToken('refresh_token', [
                                'refresh_token' => $oAuthSettings['refresh_token']
                            ]);

                            // Get string access token from the object
                            $accessToken = $accessToken->getToken();
                        } catch (\Exception) {
                            // If we tried and an error was generated - that's because refresh token isn't valid anymore
                            // e.g. user revoked access to the application
                            // so, remove the token and ask a user to login again
                            $this->_db2->delete(
                                'acl_remote_auth',
                                ['id' => $oAuthSettings['id']]
                            );

                            $accessToken = '';
                        }

                        if (!empty($accessToken)) {
                            $this->_db2->update(
                                'acl_remote_auth',
                                ['access_token' => $accessToken],
                                ['id' => $oAuthSettings['id']]
                            );
                        }
                    }
                }
            }

            if (empty($strError) && empty($accessToken)) {
                $strError = sprintf(
                    $this->_tr->translate('This email server has recently switched to a more secure authentication method, and Officio needs to obtain a permission to access your emails.<br>Please <a href="%s" target="_blank">click here</a> to log in to your email provider and grant Officio necessary access.'),
                    $this->_config['urlSettings']['baseUrl'] . '/mailer/settings/oauth-login?' .
                    http_build_query([
                        'provider' => $provider,
                        'email'    => $email,
                        'type'     => $type,
                    ])
                );
            }
        } catch (\Exception $e) {
            $accessToken = '';
            $strError    = $this->_tr->translate('Internal error');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return [$strError, $accessToken];
    }

    /**
     * Create/update access and refresh token for a specific member/host/email
     *
     * @param int $memberId
     * @param string $account email or account
     * @param string $provider microsoft or google
     * @param string $type login or imap or pop3 or smtp
     * @param string $accessToken
     * @param string $refreshToken
     * @return void
     */
    public function createUpdateAccessToken($memberId, $account, $provider, $type, $accessToken, $refreshToken)
    {
        $select = (new Select())
            ->from('acl_remote_auth')
            ->where([
                'member_id'         => $memberId,
                'provider'          => $provider,
                'remote_account_id' => $account,
                'additional_data'   => $type
            ]);

        $oAuthSettings = $this->_db2->fetchRow($select);

        if (empty($oAuthSettings['id'])) {
            $this->_db2->insert(
                'acl_remote_auth',
                [
                    'member_id'         => $memberId,
                    'provider'          => $provider,
                    'remote_account_id' => $account,
                    'access_token'      => $accessToken,
                    'refresh_token'     => $refreshToken,
                    'additional_data'   => $type,
                ],
            );
        } else {
            $this->_db2->update(
                'acl_remote_auth',
                [
                    'access_token'  => $accessToken,
                    'refresh_token' => $refreshToken,
                ],
                ['id' => $oAuthSettings['id']]
            );
        }
    }

    /**
     * Delete tokens for a specific member and email
     *
     * @param int $memberId
     * @param string $account
     * @param bool $booLogin if true - will be deleted for 'login' otherwise for email ('smtp' or 'pop3' or 'imap')
     * @return void
     */
    public function deleteTokensForMemberAndAccount($memberId, $account, $booLogin = false)
    {
        $this->_db2->delete(
            'acl_remote_auth',
            [
                'member_id'         => (int)$memberId,
                'remote_account_id' => $account,
                'additional_data'   => $booLogin ? ['login'] : ['smtp', 'pop3', 'imap'],
            ]
        );
    }

    /**
     * Delete tokens with the provided ids
     *
     * @param array|int $arrTokenIds
     * @return void
     */
    public function deleteTokens($arrTokenIds)
    {
        if (!empty($arrTokenIds)) {
            $this->_db2->delete(
                'acl_remote_auth',
                [
                    'id' => $arrTokenIds
                ]
            );
        }
    }

    /**
     * Load the list of tokens for a specific member
     *
     * @param int $memberId
     * @param bool $booLogin if true - will be deleted for 'login' otherwise for email ('smtp' or 'pop3' or 'imap')
     * @return array
     */
    public function getAccessTokens($memberId, $booLogin = false)
    {
        $select = (new Select())
            ->from('acl_remote_auth')
            ->where([
                'member_id'       => $memberId,
                'additional_data' => $booLogin ? ['login'] : ['smtp', 'pop3', 'imap'],
            ]);

        return $this->_db2->fetchAll($select);
    }
}