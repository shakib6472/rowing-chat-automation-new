<?php

namespace RowingChatAutomation;

class Plugin
{
    public $plugin_name = 'Rowing Chat Automation';
    public $plugin_slug = 'rca';

    public function init()
    {
        // add_action('init', [$this, 'post_types']);
        add_action("admin_menu", [$this, 'admin_menu'], 99);
        add_action("admin_action_{$this->plugin_slug}_start", [$this, 'start']);
        add_action("admin_footer", [$this, 'admin_footer'], 99);
        add_action("wp_ajax_rca_queue", [$this, 'ajax_queue']);
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
            "edit.php?post_type=post"
        );
    }

    public function view_main()
    {

        $categories = get_terms('category', array(
            'hide_empty' => false,
        ));
        $state = (isset($_GET['state']) ? json_decode(stripslashes($_GET['state']), true) : null);
?>
        <div class="wrap">
            <h2>Automation</h2>

            <div id="rca-response-template" style="display: none;" class="notice is-dismissible">
                <p><strong class="status"></strong> <span class="message"></span></p><button type="button"
                    class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>
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
                            <a href="<?php echo admin_url("post.php?post={$state['data']['post_id']}&action=edit") ?>">Click here to
                                edit the automatically created post</a>
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
                            console.log('Response from server:', state);
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
                            $('.starting-notice').html('<p>Upload Completed</p>');
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
        if (!wp_verify_nonce($nonce, 'rca')) {
            wp_send_json([
                'status' => 'error',
                'message' => 'Invalid nonce'
            ]);
        }
        error_log('Queue Run');

        $post_categories = isset($_POST['post_category']) ? $_POST['post_category'] : [];
        $yt_url = $_POST['yt_url'];

        error_log('Getting data for YouTube URL: ' . $yt_url);
        $state = (new QueueTask())->run($yt_url, $post_categories);

        if ($state['status'] == 'error' && !isset($state['message'])) {
            wp_send_json([
                'status' => 'error',
                'message' => "An (unexpected?) error occured. Please contact this custom plugin's developer"
            ]);
        }
        if ($state['status'] == 'success') {
            wp_send_json([
                'status' => 'success',
                'message' => "Post Uploaded successfully with url " . $state['data']['post_url']
            ]);
        }

        unset($state['output']); // Too much information

        wp_send_json($state);
    }
}
