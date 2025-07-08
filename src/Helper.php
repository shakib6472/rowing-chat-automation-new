<?php

namespace RowingChatAutomation;

require_once __DIR__ . '/../vendor/autoload.php';

class Helper
{

    public static $sc;
    public static $yt;

    public function __construct() {} 
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
