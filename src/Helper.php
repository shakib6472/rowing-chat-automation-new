<?php

namespace RowingChatAutomation;

require_once __DIR__ . '/../vendor/autoload.php';

class Helper
{

    public static $sc;
    public static $yt;

    public function __construct() {}

    public static function sc()
    {
        if (static::$sc == null) {
            $sc = new SoundCloudAPI(RCA_SC_API_URL, RCA_SC_CLIENT_ID, RCA_SC_CLIENT_SECRET);
            error_log('SoundCloudAPI initialized with URL: ' . RCA_SC_API_URL);
            error_log('SoundCloudAPI initialized with Client ID: ' . RCA_SC_CLIENT_ID);
            error_log('SoundCloudAPI initialized with Client Secret: ' . RCA_SC_CLIENT_SECRET);
            error_log('SC:' . print_r($sc, true));
            static::$sc = $sc;
        }

        // Temporary kludge
        $token = get_transient('soundcloud_token');
        $result = $sc->refresh_token($token->refresh_token);
        set_transient('soundcloud_token', $result);
        error_log('Token : ' . print_r($token, true));
        error_log('Token refreshed: ' . print_r($result, true)); 

        return static::$sc;
    }

    public static function yt()
    {  
        require_once __DIR__ . '/../vendor/autoload.php'; 

        if (static::$yt == null) { 
            $client = new \Google_Client(); 

            $client->setDeveloperKey(YT_API_KEY); 

           $service = new \Google\Service\YouTube($client);

            static::$yt = $service; 
        }  

        return static::$yt;
    }
}
