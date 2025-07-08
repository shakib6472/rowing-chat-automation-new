<?php

namespace RowingChatAutomation;

class Helpers
{
    public static function get_youtube_id($yt_url)
    {
        if (preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $yt_url, $match)) {
            $yt_id = $match[1];
            return $yt_id;
        }
        return null;
    }

    public static function get_youtube_info($yt_id)
    {

        $response = Helper::yt()->videos->listVideos('snippet,contentDetails', [
            'id' => $yt_id
        ]);


        $yt_info = $response['items'][0];
        return $yt_info;
    }
}
