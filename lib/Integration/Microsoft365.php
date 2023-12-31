<?php
declare(strict_types=1);

/**
* @copyright Copyright (c) 2023 Sebastian Krupinski <krupinski01@gmail.com>
*
* @author Sebastian Krupinski <krupinski01@gmail.com>
*
* @license AGPL-3.0-or-later
*
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU Affero General Public License as
* published by the Free Software Foundation, either version 3 of the
* License, or (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU Affero General Public License for more details.
*
* You should have received a copy of the GNU Affero General Public License
* along with this program.  If not, see <http://www.gnu.org/licenses/>.
*
*/

namespace OCA\JMAPC\Integration;

class Microsoft365 {

	const ServiceServer = 'outlook.office365.com';

    /**
	 * Connects to account, verifies details, on success saves details to user settings
	 * 
	 * @since Release 1.0.0
	 * 
	 * @param string $code		oauth authorization code
	 * 
	 * @return bool
	 */
	public static function createAccess(string $code): ?array {

        $ConfigurationService = \OC::$server->get(\OCA\JMAPC\Service\ConfigurationService::class);
        $UrlGenerator = \OC::$server->get(\OCP\IURLGenerator::class);

        $tid = $ConfigurationService->retrieveSystemValue('ms365_tenant_id');
		$aid = $ConfigurationService->retrieveSystemValue('ms365_application_id');
		$asecret = $ConfigurationService->retrieveSystemValue('ms365_application_secret');
		$code = rtrim($code,'#');

		$httpClient = (\OC::$server->get(\OCP\Http\Client\IClientService::class))->newClient();

        $response = $httpClient->post('https://login.microsoftonline.com/' . $tid . '/oauth2/v2.0/token', [
            'form_params' => [
                'client_id' => $aid,
                'client_secret' => $asecret,
                'grant_type' => 'authorization_code',
                'scope' => 'https://outlook.office.com/EAS.AccessAsUser.All offline_access openid email',
                'redirect_uri' => $UrlGenerator->getAbsoluteURL('/apps/integration_jmapc/connect-ms365'),
                'code' => $code,
            ],
        ]);

		$data = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);

		if (is_array($data)) {

            $email = '';
            $name = '';
            if (isset($data['id_token'])) {
                $id = json_decode(base64_decode(str_replace('_', '/', str_replace('-','+',explode('.', $data['id_token'])[1]))));
                if (isset($id->email)) {
                    $email = $id->email;
                }
                if (isset($id->name)) {
                    $name = $id->name;
                }
            }

			return [
                'access' => $data['access_token'],
                'expiry' => (int) $data['expires_in'] + time(),
                'refresh' => $data['refresh_token'],
                'service_server' => self::ServiceServer,
                'email' => $email,
                'name' => $name
            ];
		} else {
			return null;
		}

	}
    
    /**
	 * Reauthorize to account, verifies details, on success saves details to user settings
	 * 
	 * @since Release 1.0.0
	 * 
     * @param string $code		oauth refresh code
	 * 
	 * @return array
	 */
	public static function refreshAccess(string $code): ?array {

        $ConfigurationService = \OC::$server->get(\OCA\JMAPC\Service\ConfigurationService::class);

        $tid = $ConfigurationService->retrieveSystemValue('ms365_tenant_id');
		$aid = $ConfigurationService->retrieveSystemValue('ms365_application_id');
		$asecret = $ConfigurationService->retrieveSystemValue('ms365_application_secret');

		$httpClient = (\OC::$server->get(\OCP\Http\Client\IClientService::class))->newClient();
        $response = $httpClient->post('https://login.microsoftonline.com/' . $tid . '/oauth2/v2.0/token', [
            'form_params' => [
                'client_id' => $aid,
                'client_secret' => $asecret,
                'grant_type' => 'refresh_token',
                'scope' => 'https://outlook.office.com/EAS.AccessAsUser.All offline_access openid email',
                'refresh_token' => $code,
            ],
        ]);
		$data = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);

		if (is_array($data)) {

            $email = '';
            $name = '';
            if (isset($data['id_token'])) {
                $id = json_decode(base64_decode(str_replace('_', '/', str_replace('-','+',explode('.', $data['id_token'])[1]))));
                if (isset($id->email)) {
                    $email = $id->email;
                }
                if (isset($id->name)) {
                    $name = $id->name;
                }
            }

			return [
                'access' => $data['access_token'],
                'expiry' => (int) $data['expires_in'] + time(),
                'refresh' => $data['refresh_token'],
                'service_server' => self::ServiceServer,
                'email' => $email,
                'name' => $name
            ];
		} else {
			return null;
		}

	}

    /**
	 * Generate MS365 Authorization URL
	 * 
	 * @since Release 1.0.0
	 * 
	 * @return string
	 */
    public static function constructAuthorizationUrl(): string {
        
        // Load required modules
        $ConfigurationService = \OC::$server->get(\OCA\JMAPC\Service\ConfigurationService::class);
        $UrlGenerator = \OC::$server->get(\OCP\IURLGenerator::class);
        // retrieve required application parameters
        $tid = $ConfigurationService->retrieveSystemValue('ms365_tenant_id');
        $aid = $ConfigurationService->retrieveSystemValue('ms365_application_id');
        $asecret = $ConfigurationService->retrieveSystemValue('ms365_application_secret');
        // evaluate if required parameters
        if (!empty($tid) && !empty($aid) && !empty($asecret)) {
            // return authorization url
            return 'https://login.microsoftonline.com/' . $tid . '/oauth2/v2.0/authorize' .
                    '?client_id=' . urlencode($aid) . 
                    '&response_type=code' . 
                    '&scope=' . urlencode('https://outlook.office.com/EAS.AccessAsUser.All') .
                    '&redirect_uri=' . urlencode($UrlGenerator->getAbsoluteURL('/apps/integration_jmapc/connect-ms365'));
        }
        else {
            // return empty string
            return '';
        }
        
    }

}
