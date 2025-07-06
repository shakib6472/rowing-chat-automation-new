<?php

namespace RowingChatAutomation;

class Plugin
{
    public $plugin_name = 'Rowing Chat Automation';
    public $plugin_slug = 'rca';

    public function init()
    {
        add_action('init', [$this, 'post_types']);
        add_action("admin_menu", [$this, 'admin_menu'], 99);
        add_action("admin_action_{$this->plugin_slug}_start", [$this, 'start']);
        add_action("admin_footer", [$this, 'admin_footer'], 99);
        add_action("admin_action_rca_connect", [$this, 'soundcloud_connect']);
        add_action("wp_ajax_rca_queue", [$this, 'ajax_queue']);
        add_action("wp_ajax_rca_process", [$this, 'ajax_process']);
        add_action('admin_post_nopriv_youtube_callback', [$this, 'youtube_callback']);
        add_action('admin_post_youtube_callback', [$this, 'youtube_callback']);
    }

    public static function youtube_callback()
    {
        error_log("📥 YouTube callback received");

        // Read raw input
        $json = file_get_contents("php://input");
        $data = json_decode($json, true);
        //error log all data
        error_log('Data: ' . print_r($data, true));

        // Validate JSON
        if (!$data || !isset($data['status'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid callback payload']);
            return;
        }

        // If conversion completed
        if ($data['status'] === 'AVAILABLE') {
            $downloadUrl = $data['downloadUrl'];
            $downloadId = $data['id'];
            $format = 'mp3';

            // File will be saved as /temp/{id}.mp3
            $base_dir = plugin_dir_path(__FILE__); // Assuming callback is in same class/file
            $temp_dir = $base_dir . 'temp/';

            // Create temp dir if not exist
            if (!file_exists($temp_dir)) {
                mkdir($temp_dir, 0777, true);
            }

            $filepath = $temp_dir . $downloadId . '.' . $format;

            error_log("⬇️ Downloading file to: $filepath");

            // Fetch and save file
            $file_contents = file_get_contents($downloadUrl);
            if ($file_contents !== false) {
                file_put_contents($filepath, $file_contents);
                //Call the after_audio_download function 
                self::after_audio_download();
                error_log("✅ File saved successfully: $filepath");
            } else {
                error_log("❌ Failed to fetch download URL: $downloadUrl");
            }
        } elseif ($data['status'] === 'CONVERSION_ERROR') {
            error_log("❌ Conversion failed for ID: " . $data['id']);
        }

        // Response for RapidAPI
        echo json_encode(['status' => 'received']);
    }


    public static function after_audio_download()
    {
        $uploads = get_posts([
            'post_type'   => 'rca_upload',
            'post_status' => 'publish',
            'orderby'     => 'id',
            'order'       => 'ASC',
            'meta_query'  => [
                [
                    'key' => 'status',
                    'value' => 'processing'
                ]
            ]
        ]);

        if (count($uploads) > 0) {
            foreach ($uploads as $upload) {
                $upload_id = $upload->ID;
                error_log('Processing upload ID: ' . $upload_id);
                // Get all post meta and log it
                $all_meta = get_post_meta($upload_id);
                error_log('All post meta: ' . print_r($all_meta, true));

                $audiofilepath = add_post_meta($upload_id, 'audioid', true);
                $audiofilepath = get_post_meta($upload_id, 'audioid', true);
                error_log('Now Here Need to upload the Audio file into the SC. the link is: ' . $audiofilepath);
            }
        }
    }

    public function post_types()
    {
        $labels = array(
            'name'               => 'RCA Uploads',
            'singular_name'      => 'RCA Upload',
            'menu_name'          => 'RCA Uploads',
            'name_admin_bar'     => 'RCA Upload'
        );
        $args = array(
            'label'               => 'RCA Upload',
            'labels'              => $labels,
            'supports'            => ['title', 'author'],
            'hierarchical'        => false,
            'public'              => true,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'show_in_admin_bar'   => true,
            'show_in_nav_menus'   => true,
            'can_export'          => true,
            'has_archive'         => true,
            'exclude_from_search' => false,
            'publicly_queryable'  => true,
            'capability_type'     => 'post',
            'map_meta_cap'        => true,
            'show_in_rest'        => true,
            'rewrite'             => array('slug' => 'rca-upload')
        );
        register_post_type('rca_upload', $args);

        add_filter('manage_edit-rca_upload_columns', function ($columns) {
            if (isset($columns['title'])) {
                $columns['title'] = __('YouTube ID', 'textdomain');
            }
            $columns['yt_title'] = __('YouTube Title', 'textdomain');
            // $columns['status'] = __('Status', 'textdomain');
            $columns['state'] = __('State', 'textdomain');
            return $columns;
        });

        add_action('manage_rca_upload_posts_custom_column', function ($column, $post_id) {
            $yt_info = get_post_meta($post_id, 'yt_info', true);

            $state = get_post_meta($post_id, 'state', true);

            if ($column === 'yt_title') {
                if (! empty($yt_info['snippet']['title'])) {
                    echo esc_html($yt_info['snippet']['title']);
                }
            }

            if ($column === 'state') {
                echo '<pre>' . esc_html(json_encode(get_post_meta($post_id, 'state', true), JSON_PRETTY_PRINT)) . '</pre>';
            }
        }, 10, 2);
    }

    public function admin_menu()
    {
        add_menu_page(
            'Rowing Chat Automation',
            'Automation',
            'edit_posts',
            "{$this->plugin_slug}_view_main",
            [$this, 'view_main'],
            null,
            1
        );


        add_submenu_page(
            "{$this->plugin_slug}_view_main",
            "{$this->plugin_name} Status",
            'Status',
            'edit_posts',
            "edit.php?post_type=rca_upload"
        );
    }

    public function view_main()
    {
        error_log('[ROWCHAT] Loading main view...');
        //RowChat - SoundCloud Integration (Authorization Phase)
        $tokens = get_option('rowchat_soundcloud_tokens');

        // Debug log to check if tokens exist
        error_log('[ROWCHAT] Checking SoundCloud tokens...');
        error_log('[ROWCHAT] Tokens: ' . print_r($tokens, true));

        if (!isset($tokens['access_token'])) {
            // Not authorized yet, begin PKCE flow
            error_log('[ROWCHAT] No access_token found. Starting OAuth flow...');

            // 1. Generate code_verifier & code_challenge
            $code_verifier = bin2hex(random_bytes(64));
            $code_challenge = rtrim(strtr(base64_encode(hash('sha256', $code_verifier, true)), '+/', '-_'), '=');

            // 2. Store code_verifier in session
            if (!session_id()) {
                session_start();
            }
            $_SESSION['soundcloud_code_verifier'] = $code_verifier;

            // 3. Build authorize URL
            $params = [
                'client_id' => RCA_SC_CLIENT_ID,
                'response_type' => 'code',
                'redirect_uri' => admin_url('admin-post.php?action=soundcloud_callback'),
                'code_challenge' => $code_challenge,
                'code_challenge_method' => 'S256',
                'scope' => 'non-expiring',
            ];
            $auth_url = 'https://soundcloud.com/connect?' . http_build_query($params);

            error_log('[ROWCHAT] Redirecting to SoundCloud: ' . $auth_url);

            echo "<script>window.open('{$auth_url}', '_blank');</script>";
            echo "<noscript><a href='{$auth_url}' target='_blank'>Authorize with SoundCloud</a></noscript>";
            exit;
        } else {
            // Debug log to confirm we have a token
            error_log('[ROWCHAT] Access token found. Proceeding with automation.');
        }

        // ✅ Step 2: Continue normally if token exists
        $categories = get_terms('category', array(
            'hide_empty' => false,
        ));
        $state = (isset($_GET['state']) ? json_decode(stripslashes($_GET['state']), true) : null);
?>
        <div class="wrap">
            <h2>Automation</h2>

            <div id="rca-response-template" style="display: none;" class="notice is-dismissible">
                <p><strong class="status"></strong> <span class="message"></span></p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>
            </div>

            <div id="rca-response"></div>

            <?php if ($state): ?>
                <div class="notice notice-<?php echo $state['status'] ?> is-dismissible">
                    <p>
                        <strong><?php echo esc_html(ucfirst($state['status'])) ?>: </strong>
                        <?php if (isset($state['message'])): ?>
                            <?php echo esc_html($state['message']) ?>
                        <?php endif; ?>

                        <?php if (isset($state['data'])): ?>
                            <a href="<?php echo admin_url("post.php?post={$state['data']['post_id']}&action=edit") ?>">Click here to edit the automatically created post</a>
                        <?php endif; ?>
                    </p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Dismiss this notice.</span>
                    </button>
                </div>
            <?php endif; ?>

            <form id="rca_form" method="post" action="<?php echo admin_url('admin.php') ?>?action=rca_start">
                <?php wp_nonce_field('rca_start'); ?>

                <div>
                    <p>Step 1: Pick a category</p>
                    <fieldset>
                        <?php foreach ($categories as $category): ?>
                            <div>
                                <label><input type="checkbox" name="post_category[]" value="<?php echo $category->term_id ?>">
                                    <span><?php echo esc_html($category->name) ?></span></label>
                            </div>
                        <?php endforeach; ?>
                    </fieldset>
                </div>

                <div>
                    <p>Step 2: Copy/paste a YouTube URL</p>
                    <input type="text" name="yt_url" placeholder="YouTube URL here" required>
                </div>

                <div class="starting-notice">Starting</div>

                <div>
                    <p>Step 3: Start the task and wait until finished</p>
                    <p class="submit">
                        <input type="submit" name="submit" id="submit" class="button button-primary" value="Start Task">
                    </p>
                </div>
            </form>
        </div>
    <?php
    }

    public function view_status()
    {
        global $wpdb;
        $rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}rca_queue ORDER BY id DESC");
    ?>
        <table>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?php echo esc_html($row->yt_id) ?></td>
                    <td><?php echo esc_html($row->status) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php
    }

    public function admin_footer()
    {
    ?>
        <script type="text/javascript">
            var rca = {
                'ajaxnonce': '<?php echo wp_create_nonce('rca') ?>'
            };

            (function($) {
                $("#rca_form").on('submit', function() {
                    var $form = $(this);
                    $('.starting-notice').html('<p>Processing...Started</p>');
                    console.log('Form submitted');

                    $form.submit(false);
                    // $form.find('.submit input').prop('disabled', true);

                    $.ajax({
                            type: 'post',
                            dataType: 'json',
                            url: ajaxurl,
                            data: $form.serialize() + '&' + $.param({
                                'action': 'rca_queue',
                                'ajaxnonce': rca.ajaxnonce
                            })
                        })
                        .done(function(state) {
                            var $template = $('#rca-response-template').clone().removeAttr('id');
                            $template.addClass('notice-' + state.status);
                            $template.find('.status').text(state.status);
                            $template.find('.message').text(state.message);
                            $template.find('.notice-dismiss').on('click', function() {
                                $(this).closest('.notice').remove();
                            });
                            $template.show();
                            $('#rca-response').append($template);

                            $form.find('.submit input').prop('disabled', false);

                            // Process
                            if (state.status == 'success') {
                                $.ajax({
                                    type: 'post',
                                    dataType: 'json',
                                    url: ajaxurl,
                                    data: $.param({
                                        'action': 'rca_process',
                                        'ajaxnonce': rca.ajaxnonce,
                                        'upload_id': state.data.upload_id
                                    })
                                });
                            }
                        });

                    return false;
                });
            })(jQuery);
        </script>
<?php
    }

    public function start()
    {
        return; // Replaced with Ajax call


    }

    public function ajax_queue()
    {
        $nonce = $_POST['ajaxnonce'];
        if (! wp_verify_nonce($nonce, 'rca')) {
            wp_send_json([
                'status' => 'error',
                'message' => 'Invalid nonce'
            ]);
        }

        $post_categories = isset($_POST['post_category']) ? $_POST['post_category'] : [];
        $yt_url = $_POST['yt_url'];

        $state = (new QueueTask())->run($yt_url, $post_categories);

        if ($state['status'] == 'error' && !isset($state['message'])) {
            wp_send_json([
                'status' => 'error',
                'message' => "An (unexpected?) error occured. Please contact this custom plugin's developer"
            ]);
        }

        unset($state['output']); // Too much information

        wp_send_json($state);
    }

    public function ajax_process()
    {
        error_log('Proccess Upload Run');
        $nonce = $_POST['ajaxnonce'];
        if (! wp_verify_nonce($nonce, 'rca')) {
            wp_send_json([
                'status' => 'error',
                'message' => 'Invalid nonce'
            ]);
        }

        ignore_user_abort(true);
        set_time_limit(0);

        $upload_id = $_POST['upload_id'];
        $state = (new ProcessingTask())->process_upload($upload_id);
        wp_send_json($state);
    }

    public function soundcloud_connect()
    {
        if (isset($_GET['code']) && !empty($_GET['code'])) {
            $sc = new SoundCloudAPI(RCA_SC_API_URL, RCA_SC_CLIENT_ID, RCA_SC_CLIENT_SECRET);
            $result = $sc->auth($_GET['code']);
            set_transient('soundcloud_token', $result);
            die("Connected");
        } else {
            wp_redirect('https://secure.soundcloud.com/connect?state=encrypted_session_info&response_type=code&client_id=0ccff4c6bd150ccaab3ff01e8959acf6&redirect_uri=https%3A%2F%2Fparsed.nl%2Fsc');
        }
    }
}
