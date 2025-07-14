<?php

namespace RowingChatAutomation;

class Helpers
{
    public static function get_youtube_id(string $url): ?string
    {
        /**
         * Extract the 11–character YouTube video ID from (almost) any YouTube URL.
         *
         * Examples it accepts
         * ────────────────────
         * https://www.youtube.com/watch?v=VfZ3yw9-G6c
         * https://youtube.com/live/VfZ3yw9-G6c
         * https://youtu.be/VfZ3yw9-G6c
         * https://www.youtube.com/embed/VfZ3yw9-G6c
         * https://www.youtube.com/shorts/VfZ3yw9-G6c
         * https://www.youtube.com/v/VfZ3yw9-G6c
         *
         * @param  string $url  The raw YouTube URL supplied by the client
         * @return string|null  The 11‑character video ID, or null if not found
         */

        // 1. Try the quick patterns first (better performance than parse_url every time)
        $quickPatterns = [
            '%youtu\.be/([A-Za-z0-9_-]{11})%i',                       // youtu.be/ID
            '%youtube\.com/(?:embed|live|shorts|v)/([A-Za-z0-9_-]{11})%i',
        ];

        foreach ($quickPatterns as $pattern) {
            if (preg_match($pattern, $url, $m)) {
                return $m[1];
            }
        }

        // 2. Fallback: look for v=ID or vi=ID in the query string
        $parts = parse_url($url);
        if (isset($parts['query'])) {
            parse_str($parts['query'], $qs);
            if (!empty($qs['v']) && preg_match('/^[A-Za-z0-9_-]{11}$/', $qs['v'])) {
                return $qs['v'];
            }
            if (!empty($qs['vi']) && preg_match('/^[A-Za-z0-9_-]{11}$/', $qs['vi'])) {
                return $qs['vi'];
            }
        }

        return null; // Not a recognised YouTube video URL
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
