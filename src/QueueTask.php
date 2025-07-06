<?php

namespace RowingChatAutomation;

class QueueTask {
    public function run($yt_url, $post_categories = []) {
        global $wpdb;
   
        $yt_id = Helpers::get_youtube_id($yt_url);   
        
        if ($yt_id == null) {
            return ['status' => 'error', 'message' => 'Could not parse YouTube video ID from given URL']; 
        }  
        $yt_info = Helpers::get_youtube_info($yt_id); 
  
        // Setup processing
        $existing = get_posts([
            'post_type' => 'rca_upload',
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key'=> 'yt_id',
                    'value' => $yt_id
                ]
            ]
        ]); 
        
        if (count($existing) != 0) { 
            return [
                'status' => 'error',
                'message' => 'This upload has already been processed or is still processing'
            ];
        }
        
        $upload_id = wp_insert_post([
            'post_type' => 'rca_upload',
            'post_status' => 'publish',
            'post_title' => $yt_id,
            'meta_input' => [
                'status' => 'queued',
                'yt_id' => $yt_id,
                'yt_info' => $yt_info,
                'post_categories' => $post_categories
            ]
        ]); 
        
        return [
            'status' => 'success',
            'message' => 'Upload queued for processing',
            'data' => [
                'upload_id' => $upload_id
            ]
        ];
    }
}

