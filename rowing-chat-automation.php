<?php
/*
 * Plugin Name:      Rowing Chat Automation
 * Plugin URI:       https://github.com/shakib6472/
 * Description:      A plugin to automate functions to wordpress from youtube & soundcloud
 * Version:          1.3.0
* Requires at least: 5.2
* Requires PHP:      7.2
* Author:            Shakib Shown
* Author URI:        https://github.com/shakib6472/
* License:           GPL v2 or later
* License URI:       https://www.gnu.org/licenses/gpl-2.0.html
* Text Domain:       rca
* Domain Path:       /languages
*/

namespace RowingChatAutomationPlugin;  


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
} 

// Load Composer's autoloader
require_once('vendor/autoload.php'); 
require_once('config.php');

function init()
{
    $plugin = new \RowingChatAutomation\Plugin();
    $plugin->init();
}
add_action('plugins_loaded', __NAMESPACE__ . '\init'); 
add_action('admin_post_soundcloud_callback', 'rowchat_soundcloud_callback');

function rowchat_soundcloud_callback() {
    error_log('[ROWCHAT] Callback received...');

    if (!isset($_GET['code'])) {
        error_log('[ROWCHAT] No code in callback.');
        wp_die('No authorization code received.');
    }

    if (!session_id()) {
        session_start();
    }

    $code = sanitize_text_field($_GET['code']);
    $code_verifier = $_SESSION['soundcloud_code_verifier'] ?? null;

    error_log('[ROWCHAT] Received code: ' . $code);
    error_log('[ROWCHAT] Verifier: ' . $code_verifier);

    if (!$code_verifier) {
        error_log('[ROWCHAT] No code_verifier found.');
        wp_die('Missing PKCE verifier.');
    }

    // Exchange code for access token
    $response = wp_remote_post('https://api.soundcloud.com/oauth2/token', [
        'body' => [
            'client_id' => RCA_SC_CLIENT_ID,
            'client_secret' => RCA_SC_CLIENT_SECRET,
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => admin_url('admin-post.php?action=soundcloud_callback'),
            'code_verifier' => $code_verifier,
        ],
    ]);

    if (is_wp_error($response)) {
        error_log('[ROWCHAT] WP Error during token exchange: ' . $response->get_error_message());
        wp_die('Token exchange failed.');
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    error_log('[ROWCHAT] Token response: ' . print_r($body, true));

    if (isset($body['access_token'])) {
        // Save tokens securely
        update_option('rowchat_soundcloud_tokens', [
            'access_token' => $body['access_token'],
            'refresh_token' => $body['refresh_token'] ?? null,
            'expires_in' => $body['expires_in'] ?? null,
            'created_at' => time(),
        ]);

        error_log('[ROWCHAT] Tokens saved successfully.');
        wp_redirect(home_url('/')); // or anywhere you want
        exit;
    } else {
        error_log('[ROWCHAT] Invalid token response.');
        wp_die('Authorization failed.');
    }
}
