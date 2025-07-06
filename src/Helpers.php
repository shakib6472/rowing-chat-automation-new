<?php

namespace RowingChatAutomation;

class Helpers
{

    public static function youtube_dl($yt_url, $yt_filepath)
    {
        $callbackUrl = home_url('/wp-admin/admin-post.php?action=youtube_callback');


        $queryParams = http_build_query([
            'url' => $yt_url,
            'format' => 'mp3',
            'quality' => 0,
            'callbackUrl' => $callbackUrl
        ]);

        $api_url = 'https://youtube-to-mp315.p.rapidapi.com/download?' . $queryParams;

        $headers = [
            'X-RapidAPI-Host: youtube-to-mp315.p.rapidapi.com',
            'X-RapidAPI-Key: 12ce9622eemshed05499d5285f98p14a81fjsn1bccedb8e172',
        ];

        error_log('Starting YouTube download: ' . $yt_url);
        error_log('Request URL: ' . $api_url);

        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['status' => 'error', 'message' => $error];
        }

        $responseData = json_decode($response, true);
        error_log('Response from YouTube download API: ' . print_r($responseData, true));

        if (isset($responseData['status']) && $responseData['status'] === 'AVAILABLE') {
            return [
                'status' => 'success',
                'downloadUrl' => $responseData['downloadUrl'],
                'title' => $responseData['title'],
                'id' => $responseData['id']
            ];
        } else {
            return [
                'status' => 'pending',
                'message' => 'Conversion in progress or callback expected.',
                'id' => $responseData['id'] ?? null
            ];
        }
    }


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

    // Download YouTube video 
    public static function download_youtube_video($yt_id, $yt_filepath)
    {
        $yt_url = "https://www.youtube.com/watch?v={$yt_id}";

        $state = Helpers::youtube_dl($yt_url, $yt_filepath);
        $base_dir = plugin_dir_path(__FILE__); // Assuming callback is in same class/file
        $temp_dir = $base_dir . 'temp/';
        $temp_audio_id = $state['id'];
        $yt_filepath =  $temp_dir . $temp_audio_id .'.mp3';
        

        if ($state['status'] == 'error') {
            // Cleanup
            if (file_exists($yt_filepath)) {
                unlink($yt_filepath);
            }
            return $state;
        } else {

            $state['message'] = 'Request Succsessfull, its now under converting';
            $state['ytfilepath'] = $yt_filepath;
        }
        // 

        return $state;
    }

    public static function upload_to_soundcloud($source, $args)
    {

        error_log('Starting upload to SoundCloud: Function called with source: ' . $source);
        $sc_response = Helper::sc()->upload($source, $args);

        // Check if upload to SoundCloud went A-OK, if not error out
        if ($sc_response == null) {
            return [
                'status' => 'error',
                'message' => 'Failed to upload audio to SoundCloud'
            ];
        }

        if (property_exists($sc_response, 'errors')) {
            return [
                'status' => 'error',
                'data' => $sc_response
            ];
        }

        return [
            'status' => 'success',
            'data' => $sc_response
        ];
    }
}
