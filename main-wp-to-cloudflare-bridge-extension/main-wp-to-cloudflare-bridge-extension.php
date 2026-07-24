<?php
/**
 * Plugin Name:       MainWP Cloudflare Bridge
 * Description:       Install on your dashboard and it will allow you to pull data from Cloudflare for your MainWP reports.
 * Tested up to:      7.0.2
 * Requires at least: 6.5
 * Requires PHP:      8.0
 * Version:           1.3.7
 * Author:            Stingray82
 * Author URI:        https://github.com/stingray82
 * License:           GPLv2
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       cloudflare-to-mainwp-bridge-extension
 * Website:           https://reallyusefulplugins.com
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) exit;

class Cloudflare_MainWP_Bridge_Extension {

    private static $instance;

    public static function getInstance() {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_filter('mainwp_getsubpages_sites', array($this, 'managesites_subpage'), 10, 1);
        add_action('admin_init', array($this, 'admin_init'));
        //add_action('admin_menu', array($this, 'admin_menu'));

        // Hook to enqueue styles or scripts for admin area
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
    }

    public function admin_init() {
        // Register settings
        register_setting('cloudflare_mainwp_bridge_options_group', 'cfmwp_api_token');
        register_setting('cloudflare_mainwp_bridge_options_group', 'cloudflare-to-mainwp-bridge_allow_prerelease');

    }

    public function enqueue_admin_styles() {
        // Enqueue the style
        //wp_enqueue_style('cfmwp_Load_CSS', plugin_dir_url(__FILE__) . 'css/style.css');
    }

    public function managesites_subpage($subPage) {
        $subPage[] = array(
            'title'       => 'CFMWP Bridge',
            'slug'        => 'CFMWPBridge',
            'sitetab'     => true,
            'menu_hidden' => true,
            'callback'    => array(static::class, 'renderPage'),
        );

        return $subPage;
    }

    public static function renderPage() {
    ?>
    <div class="ui segment">
        <div class="inside">
            <form method="post" action="options.php">
                <?php
                settings_fields('cloudflare_mainwp_bridge_options_group');
                ?>
                <h3><?php _e('Cloudflare API Settings', 'cloudflare-to-mainwp-bridge-extension'); ?></h3>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><?php _e('API Token', 'cloudflare-to-mainwp-bridge-extension'); ?></th>
                        <td><input type="text" name="cfmwp_api_token" value="<?php echo esc_attr(get_option('cfmwp_api_token')); ?>" /></td>
                    </tr>
                </table>

                <h3><?php _e('Update Preferences', 'cloudflare-to-mainwp-bridge-extension'); ?></h3>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><?php _e('Enable Pre-Releases', 'cloudflare-to-mainwp-bridge'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="cloudflare-to-mainwp-bridge_allow_prerelease" value="yes" <?php checked(get_option('cloudflare-to-mainwp-bridge_allow_prerelease'), 'yes'); ?> />
                                <?php _e('Allow updates from pre-release versions', 'cloudflare-to-mainwp-bridge'); ?>
                            </label>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
    </div>
    <?php
}

}

class Cloudflare_MainWP_Bridge_Activator {

    private static $instance;
    protected $cloudflareMainWPBridgeActivated = false;
    protected $childEnabled = false;
    protected $childKey = false;
    protected $childFile;
    protected $plugin_handle = 'cloudflare-mainwp-bridge-extension';

    public static function getInstance() {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->childFile = __FILE__;
        add_filter('mainwp_getextensions', array($this, 'get_this_extension'));

        // This filter will return true if the main plugin is activated
        $this->cloudflareMainWPBridgeActivated = apply_filters('mainwp_activated_check', false);

        if ($this->cloudflareMainWPBridgeActivated !== false) {
            $this->activate_this_plugin();
        } else {
            // Listening to the 'mainwp_activated' action
            add_action('mainwp_activated', array($this, 'activate_this_plugin'));
        }
        add_action('admin_notices', array($this, 'mainwp_error_notice'));
    }

    public function get_this_extension($pArray) {
        $pArray[] = array(
            'plugin'   => __FILE__,
            'api'      => $this->plugin_handle,
            'mainwp'   => false,
            'callback' => array($this, 'settings'),
        );
        return $pArray;
    }

    public function settings() {
        do_action('mainwp_pageheader_extensions', __FILE__);
        if ($this->childEnabled) {
            Cloudflare_MainWP_Bridge_Extension::renderPage();
        } else {
            ?>
            <div class="mainwp_info-box-yellow"><?php _e('The Extension has to be enabled to change the settings.'); ?></div>
            <?php
        }
        do_action('mainwp_pagefooter_extensions', __FILE__);
    }

    public function activate_this_plugin() {
        $this->cloudflareMainWPBridgeActivated = apply_filters('mainwp_activated_check', $this->cloudflareMainWPBridgeActivated);
        $this->childEnabled = apply_filters('mainwp_extension_enabled_check', __FILE__);
        $this->childKey = $this->childEnabled['key'];

        Cloudflare_MainWP_Bridge_Extension::getInstance();
    }

    public function mainwp_error_notice() {
        global $current_screen;
        if ($current_screen->parent_base == 'plugins' && $this->cloudflareMainWPBridgeActivated == false) {
            echo '<div class="error"><p>Cloudflare MainWP Bridge Extension ' . __('requires ') . '<a href="http://mainwp.com/" target="_blank">MainWP</a>' . __(' Plugin to be activated in order to work. Please install and activate') . '<a href="http://mainwp.com/" target="_blank">MainWP</a> ' . __('first.') . '</p></div>';
        }
    }

    public function getChildKey() {
        return $this->childKey;
    }

    public function getChildFile() {
        return $this->childFile;
    }
}

// Initialize the activator
global $cloudflareMainWPBridgeActivator;
$cloudflareMainWPBridgeActivator = Cloudflare_MainWP_Bridge_Activator::getInstance();

// Hook for generating custom tokens
add_filter('mainwp_pro_reports_custom_tokens', 'cfmwp_generate_custom_analytics_tokens', 10, 4);

// Function to extract the root domain // This is now dynamic and linked to the dat file // Updates set to every 24 hours
function get_root_domain($domain) {
    $list_url = 'https://publicsuffix.org/list/public_suffix_list.dat';
    $cache_dir = WP_CONTENT_DIR . '/uploads/cache';
    if (!file_exists($cache_dir)) {
        mkdir($cache_dir, 0755, true); // Create cache directory if it doesn't exist
    }
    $cache_file = $cache_dir . '/public_suffix_list.dat'; // Change this to your cache location

    // Check if the cache file exists or is outdated
    if (!file_exists($cache_file) || time() - filemtime($cache_file) > 86400) { // Refresh every 24 hours
        $list_content = @file_get_contents($list_url);
        if ($list_content !== false) {
            file_put_contents($cache_file, $list_content);
        } else {
            throw new Exception("Failed to download the Public Suffix List.");
        }
    }

    // Load the cached suffix list
    $public_suffixes = file($cache_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    $valid_suffixes = [];
    foreach ($public_suffixes as $line) {
        $line = trim($line);
        if (strpos($line, '//') === 0) {
            continue; // Skip comments
        }
        $valid_suffixes[$line] = true;
    }

    $parts = explode('.', $domain);
    $num_parts = count($parts);

    // Start with the most specific suffix and work backward
    for ($i = 1; $i < $num_parts; $i++) {
        $potential_suffix = implode('.', array_slice($parts, $i));
        if (isset($valid_suffixes[$potential_suffix])) {
            // Match found; determine the root domain
            $root_domain = implode('.', array_slice($parts, $i - 1));
            return $root_domain;
        }
    }

    return $domain;
}



function cfmwp_generate_custom_analytics_tokens($tokensValues, $report, $site, $templ_email) {
    $api_token = get_option('cfmwp_api_token');
    if (!$api_token) {
        return $tokensValues;
    }

    $site_url = isset($site['url']) ? $site['url'] : '';
    if (!$site_url) {
        return $tokensValues;
    }

    $parsed_url = parse_url($site_url);
    $domain = isset($parsed_url['host']) ? $parsed_url['host'] : '';

    if (!$domain) {
        return $tokensValues;
    }

    $root_domain = get_root_domain($domain);

    // Get the zone ID based on the root domain
    $api_url = "https://api.cloudflare.com/client/v4/zones?name={$root_domain}";

    $response = wp_remote_get($api_url, array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_token,
            'Content-Type'  => 'application/json',
        ),
    ));

    if (is_wp_error($response)) {
        return $tokensValues;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body);

    if (!isset($data->result[0]->id)) {
        return $tokensValues;
    }

    $zone_id = $data->result[0]->id;

    $from_date = isset($report->date_from) ? date('Y-m-d', $report->date_from) : '';
    $to_date = isset($report->date_to) ? date('Y-m-d', $report->date_to) : '';

    $query = "{\"query\":\"{viewer {zones(filter: {zoneTag: \\\"$zone_id\\\"}) {httpRequests1dGroups(limit: 10, filter: {date_gt: \\\"$from_date\\\", date_lt: \\\"$to_date\\\"}) {dimensions {date} uniq { uniques } sum {requests cachedRequests cachedBytes threats bytes}}}}}\"}";

    $response = wp_remote_post('https://api.cloudflare.com/client/v4/graphql/', array(
        'body'    => $query,
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_token,
            'Content-Type'  => 'application/json',
        ),
    ));

    if (is_wp_error($response)) {
        return $tokensValues;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body);

    if (isset($data->data->viewer->zones[0]->httpRequests1dGroups[0]->sum)) {
        $analytics = $data->data->viewer->zones[0]->httpRequests1dGroups[0]->sum;
        $uniq = $data->data->viewer->zones[0]->httpRequests1dGroups[0]->uniq;

        // Set token values
        $tokensValues['[cfmwp-requests]'] = $analytics->requests;
        $tokensValues['[cfmwp-uniques]'] = $uniq->uniques;
        $tokensValues['[cfmwp-cached]'] = $analytics->cachedRequests;
        $tokensValues['[cfmwp-bandwidth]'] = cfmwp_format_bandwidth($analytics->bytes); // Use the new formatting function here
        $tokensValues['[cfmwp-attacks]'] = $analytics->threats;

        
        // Pass all analytics data via a filter
        $all_analytics = array(
            'requests'      => $analytics->requests,
            'uniques'       => $uniq->uniques,
            'cached'        => $analytics->cachedRequests,
            'bandwidth'     => $analytics->bytes,
            'attacks'       => $analytics->threats,
        );
        
         //Use add_filter to register the data for custom hook
        add_filter('cfmwp_all_analytics_data', function () use ($all_analytics) {
            return $all_analytics;
        });
        
        //error_log('From Plugin- CFMWP Analytics Data: ' . print_r($all_analytics, true));
        
    }

    return $tokensValues;
}


function cfmwp_format_bandwidth($bytes) {
    $units = array('bytes', 'KB', 'MB', 'GB', 'TB');
    $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
    $value = $bytes / pow(1024, $power);
    return round($value, 2) . ' ' . $units[$power];
}

// Define plugin constants
define('RUP_MAINWP_CLF_BRIDGE_VERSION', '1.3.7');

// ──────────────────────────────────────────────────────────────────────────
//  Updater bootstrap (plugins_loaded priority 1):
// ──────────────────────────────────────────────────────────────────────────
add_action( 'plugins_loaded', function() {
    // 1) Load our universal drop-in. Because that file begins with "namespace UUPD\V1;",
    //    both the class and the helper live under UUPD\V1.
    require_once __DIR__ . '/inc/updater.php';

    // 2) Build a single $updater_config array:
    $updater_config = [
    	'vendor'	  => 'RUP',
        'plugin_file' => plugin_basename( __FILE__ ),             // e.g. "simply-static-export-notify/simply-static-export-notify.php"
        'slug'        => 'mainwp-bridge-to-cloudflare-bridge',           // must match your updater‐server slug
        'name'        => 'MainWP Cloudflare Bridge',         // human‐readable plugin name
        'version'     => RUP_MAINWP_CLF_BRIDGE_VERSION, // same as the VERSION constant above
        'key'         => '',                 // your secret key for private updater
        'server'      => 'https://raw.githubusercontent.com/stingray82/MainWP-Bridge-to-Cloudflare-Bridge/main/uupd/index.json',
    ];

    // 3) Call the helper in the UUPD\V1 namespace:
    \RUP\Updater\Updater_V2::register( $updater_config );
}, 20 );

add_filter(
    'uupd/allow_prerelease/rup/mainwp-bridge-to-cloudflare-bridge',
    function ( $allow, $vendor, $slug, $instance_key ) {

        $option = get_option( 'cloudflare-to-mainwp-bridge-extension_allow_prerelease', 'no' );

        return $option === 'yes';

    },
    10,
    4
);
