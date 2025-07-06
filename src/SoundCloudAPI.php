<?php

namespace RowingChatAutomation;

class SoundCloudAPI
{
    private $url;
    private $clientID;
    private $secret;
    private $accessToken;

    public function __construct($url, $clientID, $secret)
    {
        error_log('SoundCloudAPI: Initializing with URL: ' . $url);
        $this->url = $url;
        $this->clientID = $clientID;
        $this->secret = $secret;

        $token = get_transient('soundcloud_token');
        error_log('SoundCloudAPI: Token retrieved with get_transient: ' . print_r($token, true));
        if ($token && isset($token->access_token)) {
            $this->accessToken = $token->access_token;
            error_log('SoundCloudAPI: Access token set with token->access_token: ' . $this->accessToken);
        }
    }


    public function auth($code)
    {
        $url = $this->url . '/oauth2/token';
        $data = array(
            'client_id' => $this->clientID,
            'client_secret' => $this->secret,
            // SC deprecated the password grant type and recommends using authorization_code 
            // for client-side integrations and client_credentials for server-side integrations.
            // 'grant_type' => 'password', 
            // 'username' => $username,
            // 'password' => $password
            'grant_type' => 'authorization_code',
            'redirect_uri' => 'https://parsed.nl/sc',
            'code' => $code
        );

        $result = $this->request($url, $data, 'POST');

        $this->accessToken = $result->access_token;

        return $result;
    }

    public function refresh_token($refresh_token)
    {
        $url = $this->url . '/oauth2/token';
        $data = array(
            'client_id' => $this->clientID,
            'client_secret' => $this->secret,
            'grant_type' => 'refresh_token',
            'refresh_token' => $refresh_token
        );

        $result = $this->request($url, $data, 'POST');

        $this->accessToken = $result->access_token;

        return $result;
    }

    public function upload($path, $metadata = [])
    {
        $url = $this->url . '/tracks';
        $data = array_merge(array(
            'oauth_token' => $this->accessToken,
            'track[asset_data]' => new \CurlFile(realpath($path))
        ), $metadata);

        $result = $this->request($url, $data, 'POST');

        return $result;
    }

    public function get_track($id)
    {
        $url = "{$this->url}/tracks/{$id}";

        $data = [
            'oauth_token' => $this->accessToken,
        ];

        $result = $this->request($url, $data, 'GET');
        return $result;
    }

    public function update_track($id, $data)
    {
        $url = "{$this->url}/tracks/{$id}";

        $data = array_merge([
            'oauth_token' => $this->accessToken,
        ], $data);

        $result = $this->request($url, $data, 'PUT');
        return $result;
    }

    private function request($url, $data, $method)
    {
        $curl = curl_init();

        $token = get_transient('soundcloud_token');

        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Authorization: OAuth ' . $token->access_token
        ]);

        if ($method === 'POST') {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        } else if ($method === 'PUT') {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
        } else {
            $url .= '?' . http_build_query($data);
        }
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        $response = curl_exec($curl);
        $result = json_decode($response);
        curl_close($curl);

        return $result;
    }
}
