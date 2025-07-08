<?php

namespace RowingChatAutomation;

class QueueTask
{
    public function run($yt_url, $post_categories = [])
    {
        global $wpdb;
        error_log('Just Run the Run Function');
        error_log('Getting data for YouTube URL: ' . $yt_url);
        $yt_id = Helpers::get_youtube_id($yt_url);
        // If the YouTube ID could not be parsed, return an error
        error_log('Got The YouTube ID: ' . $yt_id);
        if ($yt_id == null) {
            return ['status' => 'error', 'message' => 'Could not parse YouTube video ID from given URL'];
        }
        // Get YouTube video info
        error_log('Getting YouTube video info for ID: ' . $yt_id);
        $yt_info = Helpers::get_youtube_info($yt_id);
        // Log the YouTube video ID 
        // Let's Extract the details from the YouTube info
        $title         = $yt_info->getSnippet()->getTitle();
        $description   = $yt_info->getSnippet()->getDescription();
        $tags          = $yt_info->getSnippet()->getTags(); // array
        $thumbnails    = $yt_info->getSnippet()->getThumbnails(); // object
        $duration      = $yt_info->getContentDetails()->getDuration(); // ISO 8601 string 
        $maxres_thumb   = $thumbnails->getMaxres() ? $thumbnails->getMaxres()->getUrl() : null;
        $readable_duration = $this->convertYouTubeDuration($duration);

        //prepare full content with description and youtube url
       $full_content = $description . "\n\n" . $yt_url;

        //upload the thumbnails to the media library
        $thumbnail_id = $this->upload_youtube_thumbnail($maxres_thumb, 0, $title);
        error_log('Thumbnail ID: ' . $thumbnail_id);
        // Setup processing
        $existing = get_posts([
            'post_type' => 'post',
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key' => 'yt_id',
                    'value' => $yt_id
                ]
            ]
        ]);

        if (count($existing) != 0) {
            return [
                'status' => 'error',
                'message' => 'This Post is already Uploaded'
            ];
        }

        $upload_id = wp_insert_post([
            'post_type'     => 'post',
            'post_status'   => 'publish',
            'post_title'    => $title,
            'post_content'  => $full_content, // Corrected key
            'post_excerpt'  => $readable_duration,
            'post_category' => $post_categories, // Array of category IDs
            'meta_input'    => [
                'status'     => 'queued',
                'yt_id'      => $yt_id,
                'yt_info'    => json_encode($yt_info), // Store safely
            ]
        ]);

        // ✅ Set featured image
        if ($thumbnail_id) {
            set_post_thumbnail($upload_id, $thumbnail_id);
        }

        // ✅ Set tags
        if (!empty($tags)) {
            wp_set_post_tags($upload_id, $tags);
        }

        //the post id is the upload_id
        // now add contents here.
        error_log('Just finished the Run Function');


        return [
            'status' => 'success',
            'message' => 'Post Uploaded successfully',
            'data' => [
                'upload_id' => $upload_id,
                'post_url' => get_permalink($upload_id)
            ]
        ];
    } 
    public function convertYouTubeDuration($duration)
    {
        $interval = new \DateInterval($duration);
        $minutes = $interval->i;
        $seconds = $interval->s;
        return sprintf('%02d:%02d', $minutes, $seconds);
    } 
    public function upload_youtube_thumbnail($image_url, $post_id = 0, $post_title = '')
    {
        if (empty($image_url)) return 0;
        // Get file name and download the image
        $tmp = download_url($image_url);
        // Handle download errors
        if (is_wp_error($tmp)) {
            return 0;
        }
        // Get the image name from the URL
        $url_parts = explode('/', $image_url);
        $filename = end($url_parts);
        // Get the correct file type
        $file_array = [
            'name'     => $filename,
            'tmp_name' => $tmp
        ];

        // Check file type and upload
        $file_type = wp_check_filetype($filename, null);

        // Upload to media library
        $attachment_id = media_handle_sideload($file_array, $post_id);

        // Clean up if there was an error
        if (is_wp_error($attachment_id)) {
            @unlink($tmp);
            return 0;
        }

        return $attachment_id;
    }
}
