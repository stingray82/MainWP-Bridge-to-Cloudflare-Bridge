<?php
/**
 * MainWP Cloudflare Bridge Extension
 *
 * @author        Stingray82
 * @license       gplv2
 * @version       1.0.0
 *
 * @wordpress-plugin
 * Plugin Name:   MainWP Cloudflare Bridge
 * Plugin URI:    https://github.com/stingray82/Cloudflare-MainWP-Bridge
 * Description:   Install on your dashboard and it will allow you to pull data from Cloudflare for your MainWP reports.
 * Version:       1.0.0
 * Author:        Stingray82
 * Author URI:    https://github.com/stingray82
 * Text Domain:   cloudflare-to-mainwp-bridge-extension
 * Domain Path:   /languages
 * License:       GPLv2
 * License URI:   https://www.gnu.org/licenses/gpl-2.0.html
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
    }

    public function enqueue_admin_styles() {
        // Enqueue the style
        wp_enqueue_style('cfmwp_Load_CSS', plugin_dir_url(__FILE__) . 'css/style.css');
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
                    do_settings_sections('cloudflare_mainwp_bridge_options_group');
                    ?>
                    <h3><?php _e('Cloudflare API Settings', 'cloudflare-to-mainwp-bridge-extension'); ?></h3>
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row"><?php _e('API Token', 'cloudflare-to-mainwp-bridge-extension'); ?></th>
                            <td><input type="text" name="cfmwp_api_token" value="<?php echo esc_attr(get_option('cfmwp_api_token')); ?>" /></td>
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

// Function to extract the root domain
function get_root_domain($domain) {
    $parts = explode('.', $domain);
    $num_parts = count($parts);

    // Handle cases like 'childsite.wptv.uk' and 'www.example.co.uk'
    if ($num_parts > 2) {
        $tld = $parts[$num_parts - 1]; // Top-level domain (e.g., 'uk')
        $sld = $parts[$num_parts - 2]; // Second-level domain (e.g., 'co', 'org')

        // Common SLDs like co.uk, org.uk, etc.
        $common_slds = array('co', 'org', 'gov', 'ac');

        if (in_array($sld, $common_slds)) {
            // If the domain is 'example.co.uk', return 'example.co.uk'
            $root_domain = $parts[$num_parts - 3] . '.' . $sld . '.' . $tld;
        } else {
            // Otherwise, return 'example.com' or 'example.uk'
            $root_domain = $sld . '.' . $tld;
        }
    } else {
        $root_domain = $domain;
    }

    return $root_domain;
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
    }

    return $tokensValues;
}


function cfmwp_format_bandwidth($bytes) {
    $units = array('bytes', 'KB', 'MB', 'GB', 'TB');
    $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
    $value = $bytes / pow(1024, $power);
    return round($value, 2) . ' ' . $units[$power];
}