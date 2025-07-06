<?php

namespace RowingChatAutomation;

class ProcessingTask {
    public $running = false;
    public $base_dir = null;
    
    public function __construct() {
        $this->base_dir = ABSPATH . "wp-content/plugins/rowing-chat-automation";
    }
    
    public function signal_handler($signo) {
        $this->running = false;
        printf("Notice: interrupt received, killing process...%s", PHP_EOL);
        error_log("Rowing Chat Automation: Process interrupted by signal {$signo}");
    }
    
    public function run() {
        $this->running = true;
        
        pcntl_signal(SIGINT, [&$this, 'signal_handler']);
        
        while ($this->running) {
            $uploads = get_posts([
                'post_type'   => 'rca_upload',
                'post_status' => 'publish',
                'orderby'     => 'id',
                'order'       => 'ASC',
                'meta_query'  => [
                    [
                        'key'=> 'status',
                        'value' => 'queued'
                    ]
                ]
            ]);
            
            if (count($uploads) > 0) {
                foreach ($uploads as $upload) {
                    if ($this->running) {
                        $this->process_upload($upload->ID);
                        pcntl_signal_dispatch();
                    }
                }
            }
            
            sleep(10);
            pcntl_signal_dispatch();
            
            if ($this->running == false) {
                break;
            }
        }
    }


    public function process_upload($upload_id) {

        update_post_meta($upload_id, 'status', 'processing'); 
        $upload = get_post($upload_id);
        $yt_id = get_post_meta($upload_id, 'yt_id', true);
        $yt_info = get_post_meta($upload_id, 'yt_info', true);
        $yt_filepath = "{$this->base_dir}/temp/{$yt_id}.m4a"; 
        $sc_metadata = get_post_meta($upload_id, 'sc_metadata', true);
        $uploaded_post_id = get_post_meta($upload_id, 'uploaded_post_id', true);
        $post_categories = get_post_meta($upload_id, 'post_categories', true);
        
        // Download YouTube video (skips existing)
        $state = Helpers::download_youtube_video($yt_id, $yt_filepath);
        $yt_filepath = $state['ytfilepath'];
        error_log('Yt file Path: ' . $yt_filepath);
        update_post_meta($upload_id, 'audioid', $yt_filepath);

        if ($state['status'] != 'success') {
            update_post_meta($upload_id, 'status', 'error');
            update_post_meta($upload_id, 'state', $state); 
            return $state;
        }

        // Upload to SoundCloud
        if ($sc_metadata == null) {
            error_log('Starting upload to SoundCloud');   

            $sc_tag_list = '';
            if ($yt_info['snippet']['tags'] != null) {
                $sc_tag_list = implode(' ', array_map(function($tag) {
                    return   '"' . $tag . '"';
                }, $yt_info['snippet']['tags']));
            }
            
            $sc_args = [
                'track[created_at]' => (new \DateTime($yt_info['snippet']['publishedAt']))->format('Y/m/d H:i:s O'),
                'track[title]' => $yt_info['snippet']['title'],
                'track[description]' => $yt_info['snippet']['description'],
                'track[genre]' => 'Sports',
                'track[sharing]' => 'public', // public/private // FIXME: as of some time updating this property is no longer possible
                'track[tag_list]' => $sc_tag_list
            ];
            
            $state = Helpers::upload_to_soundcloud($yt_filepath, $sc_args);
            
            if ($state['status'] != 'success') {
                update_post_meta($upload_id, 'status', 'error');
                update_post_meta($upload_id, 'state', $state);
                return $state;
            }
            
            $sc_metadata = $state['data'];
            update_post_meta($upload_id, 'sc_metadata', $sc_metadata);
        }

        $sc_id = $sc_metadata->id; 

        // Generate embed codes
        $embed_codes = [];

        $yt_embed = '<iframe src="https://www.youtube.com/embed/' . $yt_id . '" width="560" height="315" frameborder="0" allowfullscreen="allowfullscreen"></iframe>';
        $embed_codes[] = $yt_embed;

        $sc_secret_uri = $sc_metadata->secret_uri;
        $sc_embed_url = "https://w.soundcloud.com/player/?" . http_build_query([
            'url' => 'https://api.soundcloud.com/tracks/' . $sc_id, // Used to be $sc_metadata->secret_uri
            'auto_play' => 'false',
            'hide_related' => 'false',
            'show_comments' => 'true',
            'show_user' => 'true',
            'show_reposts' => 'false',
            'show_teaser' => 'true'
        ]);

        $sc_embed = '<iframe width="100%" height="166" scrolling="no" frameborder="no" allow="autoplay" src="' . $sc_embed_url . '"></iframe>';
        $embed_codes[] = $sc_embed;
        
        //
        // Create WordPress post
        //
        if ($uploaded_post_id == null) {
            // Create tags
            $tag_slugs = [];
            foreach ($yt_info['snippet']['tags'] as $tag) {
                $tag_slugs[] = sanitize_title($tag);
                wp_insert_term($tag, 'post_tag', ['slug' => sanitize_title($tag)]);
            }

            // Create links from URLs
            $description = $yt_info['snippet']['description'];
            $description = preg_replace(
                '#(https?://[^\s]+)#i', // The most simple regexp I could think of
                '<a href="$1">$1</a>',
                $description
            );
            
            $post_id = wp_insert_post([
                'post_date'     => (new \DateTime($yt_info['snippet']['publishedAt']))->format('Y-m-d H:i:s'),
                'post_title'    => $yt_info['snippet']['title'],
                'post_content'  => $description . "\n" . implode('\n', $embed_codes),
                'post_status'   => 'publish',
                'post_category' => $post_categories,
                'post_author'   => $upload->post_author,
                'tags_input'    => $tag_slugs,
                'meta_input'    => [
                    'rct-mailchimp' => 1
                ]
            ]);
            
            $uploaded_post_id = $post_id;
            update_post_meta($upload_id, 'uploaded_post_id', $uploaded_post_id);
            
            // Set the cover image from YouTube as the featured image
            $parent_post_id = $post_id;
            $filepath = wp_upload_dir()['basedir'] . "/youtube/{$yt_id}.jpg";
            $file = fopen($filepath, 'w');

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_FAILONERROR => true,
                CURLOPT_FILE        => $file,
                CURLOPT_URL         => "https://i.ytimg.com/vi/{$yt_id}/maxresdefault.jpg",
            ]);
            curl_exec($ch);
            $curl_errno = curl_errno($ch);
            if ($curl_errno !== 0) {
                $curl_error = curl_error($ch);
            }
            curl_close($ch);
            fclose($file);

            if ($curl_errno === 0) {
                $filetype = wp_check_filetype(basename($filepath), null);
                            
                $attachment = [
                    'post_mime_type' => $filetype['type'],
                    'post_parent'    => $parent_post_id,
                    'post_title'     => $yt_info['snippet']['title'],
                    'post_content'   => '',
                    'post_status'    => 'inherit',
                ];

                $attach_id = wp_insert_attachment( $attachment, $filepath, $parent_post_id);

                if (!is_wp_error($attach_id)) {
                    $attach_data = wp_generate_attachment_metadata( $attach_id, $filepath );
                    wp_update_attachment_metadata( $attach_id, $attach_data );
                    
                    set_post_thumbnail($parent_post_id, $attach_id);
                }
            }
        }
        
        $state = [
            'status' => 'success',
            'data' => [
                'post_id' => $uploaded_post_id
            ]
        ];
        
        update_post_meta($upload_id, 'status', 'finished');
        update_post_meta($upload_id, 'state', $state);
        
        return $state;
    }
}
