<?php
/**
 * Universal Updater Drop-In (UUPD) for Plugins & Themes
 * --------------------------------------------------------
 * Supports:
 *  - Private update servers (via JSON metadata)
 *  - GitHub-based updates (auto-detected via `server` URL)
 *  - Manual update triggers
 *  - Caching via WordPress transients
 *  - Optional GitHub authentication (for private repos or rate-limiting)
 *
 * Safe to include multiple times. Class is namespaced and encapsulated.
 *
 * ╭───────────────────────────── GitHub Token Filters ─────────────────────────────╮
 *
 * ➤ Override GitHub tokens globally or per plugin slug:
 *
 *   // A. Apply a single fallback token for all GitHub plugins:
 *   add_filter( 'uupd/github_token_override', function( $token, $slug ) {
 *       return 'ghp_yourGlobalFallbackToken';
 *   }, 10, 2 );
 *
 *   // B. Apply per-slug tokens only when needed:
 *   add_filter( 'uupd/github_token_override', function( $token, $slug ) {
 *       $tokens = [
 *           'plugin-slug-1' => 'ghp_tokenForPlugin1',
 *           'plugin-slug-2' => 'ghp_tokenForPlugin2',
 *       ];
 *       return $tokens[ $slug ] ?? $token;
 *   }, 10, 2 );
 *
 * ╰────────────────────────────────────────────────────────────────────────────────╯
 *
 * ╭──────────────────────────── Plugin Integration ─────────────────────────────╮
 *
 * 1. Save this file to: `includes/updater.php` inside your plugin.
 *
 * 2. In your main plugin file (e.g. `my-plugin.php`), add:
 *
 *    add_action( 'plugins_loaded', function() {
 *        require_once __DIR__ . '/includes/updater.php';
 *
 *        $updater_config = [
 *            'plugin_file'   => plugin_basename( __FILE__ ),     // e.g. "my-plugin/my-plugin.php"
 *            'slug'          => 'my-plugin-slug',                // must match your update slug
 *            'name'          => 'My Plugin Name',                // shown in the update UI
 *            'version'       => MY_PLUGIN_VERSION,               // define as constant
 *            'key'           => 'YourSecretKeyHere',             // optional if using GitHub
 *            'server'        => 'https://github.com/user/repo',  // GitHub or private server
 *            'github_token'  => 'ghp_YourTokenHere',             // optional
 *            // 'textdomain' => 'my-plugin-textdomain',         // optional, defaults to 'slug'
 *        ];
 *
 *        \RUP\Updater\Updater_V1::register( $updater_config );
 *    }, 1 );
 *
 * ╰─────────────────────────────────────────────────────────────────────────────╯
 *
 * ╭──────────────────────────── Theme Integration ──────────────────────────────╮
 *
 * 1. Save this file to: `includes/updater.php` inside your theme.
 *
 * 2. In your `functions.php`, add:
 *
 *    add_action( 'after_setup_theme', function() {
 *        require_once get_stylesheet_directory() . '/includes/updater.php';
 *
 *        $updater_config = [
 *            'slug'         => 'my-theme-folder',                // must match theme folder
 *            'name'         => 'My Theme Name',
 *            'version'      => '1.0.0',                           // match style.css Version
 *            'key'          => 'YourSecretKeyHere',              // optional if using GitHub
 *            'server'       => 'https://github.com/user/repo',   // GitHub or private
 *            'github_token' => 'ghp_YourTokenHere',              // optional
 *            // 'textdomain' => 'my-theme-textdomain',
 *        ];
 *
 *        add_action( 'admin_init', function() use ( $updater_config ) {
 *            \RUP\Updater\Updater_V1::register( $updater_config );
 *        } );
 *    } );
 *
 * ╰─────────────────────────────────────────────────────────────────────────────╯
 *
 *  * ╭────────────────────────────── Cache Duration Filters ───────────────────────────────╮
 *
 * ➤ Customize how long update data is cached (success or failure) per slug or globally:
 *
 *   // A. Change success cache duration (default: 6 hours):
 *   add_filter( 'urup_success_cache_ttl', function( $ttl, $slug ) {
 *       if ( $slug === 'my-plugin-slug' ) {
 *           return 1 * HOUR_IN_SECONDS; // Cache successful metadata for 1 hour
 *       }
 *       return $ttl;
 *   }, 10, 2 );
 *
 *   // B. Change error cache duration (e.g., if remote server is unreachable):
 *   add_filter( 'urup_fetch_remote_error_ttl', function( $ttl, $slug ) {
 *       return 15 * MINUTE_IN_SECONDS; // Retry failed fetches after 15 minutes
 *   }, 10, 2 );
 *
 * ➤ These filters help balance performance and responsiveness for different update sources.
 *
 * ╰──────────────────────────────────────────────────────────────────────────────────────╯ 
 * 
 * 
 * 
 * 
 * 🔧 Optional Debugging:
 *     Add this anywhere in your code:
 *         add_filter( 'updater_enable_debug', fn( $e ) => true );
 *
 *     Also enable in wp-config.php:
 *         define( 'WP_DEBUG', true );
 *         define( 'WP_DEBUG_LOG', true ); * 
 * 
 * What This Does:
 *  - Detects updates from GitHub or private JSON endpoints
 *  - Auto-selects GitHub logic if `server` contains "github.com"
 *  - Caches metadata in `rup_{slug}` for 6 hour
 *  - Injects WordPress update data via native transients
 *  - Adds “View details” + “Check for updates” under plugin/theme row
 *  - Works seamlessly with `wp_update_plugins()` or `wp_update_themes()`
 */

namespace RUP\Updater;

if ( ! class_exists( __NAMESPACE__ . '\Updater_V1' ) ) {

    class Updater_V1 {

        const VERSION = '1.2.5'; // Change as needed

        /** @var array Configuration settings */
        private $config;

        /**
         * Constructor.
         *
         * @param array $config {
         *   @type string 'slug'        Plugin or theme slug.
         *   @type string 'name'        Human-readable name.
         *   @type string 'version'     Current version.
         *   @type string 'key'         Your secret key.
         *   @type string 'server'      Base URL of your updater endpoint.
         *   @type string 'plugin_file' (optional) plugin_basename(__FILE__) for plugins.
         * }
         */
        public function __construct( array $config ) {
            $this->config = $config;
            $this->log( "✓ Using Updater_V1 version " . self::VERSION );
            $this->register_hooks();
        }

        /** Attach update and info filters for plugin or theme. */
        private function register_hooks() {
            if ( ! empty( $this->config['plugin_file'] ) ) {
                add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'plugin_update' ] );
                add_filter( 'site_transient_update_plugins',     [ $this, 'plugin_update' ] ); // WP 6.8
                add_filter( 'plugins_api',                       [ $this, 'plugin_info' ], 10, 3 );
            } else {
                add_filter( 'pre_set_site_transient_update_themes', [ $this, 'theme_update' ] );
                add_filter( 'site_transient_update_themes',          [ $this, 'theme_update' ] ); // WP 6.8
                add_filter( 'themes_api',                            [ $this, 'theme_info' ], 10, 3 );
            }
        }

        /** Fetch metadata JSON from remote server and cache it. */
        private function fetch_remote() {
            $c    = $this->config;
            $slug = rawurlencode( $c['slug'] );
            $key = rawurlencode( isset( $c['key'] ) ? $c['key'] : '' );
            $host = rawurlencode( wp_parse_url( untrailingslashit( home_url() ), PHP_URL_HOST ) );
            $separator = strpos( $c['server'], '?' ) === false ? '?' : '&';
            $url = ( str_ends_with( $c['server'], '.json' ) ? $c['server'] : untrailingslashit( $c['server'] ) )
                . $separator . "action=get_metadata&slug={$slug}&key={$key}&domain={$host}";

            $failure_cache_key = 'rup_' . $c['slug'] . '_error';

            $this->log( " Fetching metadata: {$url}" );
            $resp = wp_remote_get( $url, [
                'timeout' => 15,
                'headers' => [ 'Accept' => 'application/json' ],
            ] );

            if ( is_wp_error( $resp ) ) {
                $msg = $resp->get_error_message();
                $this->log( " WP_Error: $msg — caching failure for 6 hours" );
                $ttl = apply_filters( 'urup_fetch_remote_error_ttl', 6 * HOUR_IN_SECONDS, $slug );
                set_transient( $failure_cache_key, time(), $ttl );
                do_action( 'urup_metadata_fetch_failed', [ 'slug' => $c['slug'], 'server' => $c['server'], 'message' => $msg ] );
                return;
            }

            $code = wp_remote_retrieve_response_code( $resp );
            $body = wp_remote_retrieve_body( $resp );

            $this->log( "← HTTP {$code}: " . trim( $body ) );

            if ( 200 !== (int) $code ) {
                $this->log( "Unexpected HTTP {$code} — update fetch will pause until next cycle" );
                $ttl = apply_filters( 'urup_fetch_remote_error_ttl', 6 * HOUR_IN_SECONDS, $slug );
                set_transient( $failure_cache_key, time(), $ttl );
                do_action( 'urup_metadata_fetch_failed', [ 'slug' => $c['slug'], 'server' => $c['server'], 'code' => $code ] );
                return;
            }

            $meta = json_decode( $body );
            if ( ! $meta ) {
                $this->log( ' JSON decode failed — caching error state' );
                $ttl = apply_filters( 'urup_fetch_remote_error_ttl', 6 * HOUR_IN_SECONDS, $slug );
                set_transient( $failure_cache_key, time(), $ttl );
                do_action( 'urup_metadata_fetch_failed', [ 'slug' => $c['slug'], 'server' => $c['server'], 'code' => 200, 'message' => 'Invalid JSON' ] );
                return;
            }

            set_transient( 'rup_' . $c['slug'], $meta, 6 * HOUR_IN_SECONDS );
            delete_transient( $failure_cache_key );
            $this->log( " Cached metadata '{$c['slug']}' → v" . ( $meta->version ?? 'unknown' ) );
        }


        /** Handle plugin update injection. */
        public function plugin_update( $trans ) {
            if ( ! is_object( $trans ) || ! isset( $trans->checked ) || ! is_array( $trans->checked ) ) {
                return $trans;
            }

            $c         = $this->config;
            $file      = $c['plugin_file'];
            $slug      = $c['slug'];
            $cache_id  = 'rup_' . $slug;
            $error_key = $cache_id . '_error';

            $this->log( "Plugin-update hook for '{$slug}'" );

            $current = $trans->checked[ $file ] ?? $c['version'];
            $meta    = get_transient( $cache_id );

            //  Skip if last fetch failed
            if ( false === $meta && get_transient( $error_key ) ) {
                $this->log( " Skipping plugin update check for '{$slug}' — previous error cached" );
                return $trans;
            }

            // Fetch metadata if missing
            if ( false === $meta ) {
                if ( isset( $c['server'] ) && strpos( $c['server'], 'github.com' ) !== false ) {
                    $repo_url  = rtrim( $c['server'], '/' );
                    $cache_key = 'urup_github_release_' . md5( $repo_url );
                    $release   = get_transient( $cache_key );

                    if ( false === $release ) {
                        $api_url = str_replace( 'github.com', 'api.github.com/repos', $repo_url ) . '/releases/latest';
                        $token   = apply_filters( 'uupd/github_token_override', $c['github_token'] ?? '', $slug );

                        $headers = [ 'Accept' => 'application/vnd.github.v3+json' ];
                        if ( $token ) {
                            $headers['Authorization'] = 'token ' . $token;
                        }

                        $this->log( " GitHub fetch: $api_url" );
                        $response = wp_remote_get( $api_url, [ 'headers' => $headers ] );

                        if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
                            $release = json_decode( wp_remote_retrieve_body( $response ) );
                            $ttl = apply_filters( 'urup_fetch_remote_error_ttl', 6 * hoUR_IN_SECONDS, $slug );
                            set_transient( $error_key, time(), $ttl );

                        } else {
                            $msg = is_wp_error( $response ) ? $response->get_error_message() : 'Invalid HTTP response';
                            $this->log( "✗ GitHub API failed — $msg — caching error state" );
                            set_transient( $error_key, time(), 6 * HOUR_IN_SECONDS );
                            do_action( 'urup_metadata_fetch_failed', [ 'slug' => $slug, 'server' => $repo_url, 'message' => $msg ] );
                            return $trans;
                        }
                    }

                    if ( isset( $release->tag_name ) ) {
                        $zip_url = $release->zipball_url;
                        foreach ( $release->assets ?? [] as $asset ) {
                            if ( str_ends_with( $asset->name, '.zip' ) ) {
                                $zip_url = $asset->browser_download_url;
                                break;
                            }
                        }

                        $meta = (object) [
                            'version'       => ltrim( $release->tag_name, 'v' ),
                            'download_url'  => $zip_url,
                            'homepage'      => $release->html_url ?? $repo_url,
                            'sections'      => [ 'changelog' => $release->body ?? '' ],
                        ];
                    } else {
                        $meta = (object) [
                            'version'       => $c['version'],
                            'download_url'  => '',
                            'homepage'      => $repo_url,
                            'sections'      => [ 'changelog' => '' ],
                        ];
                    }

                    set_transient( $cache_id, $meta, apply_filters( 'urup_success_cache_ttl', 6 * HOUR_IN_SECONDS, $slug ) );
                    delete_transient( $error_key );

                } else {
                    $this->fetch_remote(); // Handles error logging + failure cache internally
                    $meta = get_transient( $cache_id );
                }
            }

            // If no new version or bad data, say "no update"
            if ( ! $meta || version_compare( $meta->version ?? '0.0.0', $current, '<=' ) ) {
                $this->log( "Plugin '{$slug}' is up to date (v$current)" );
                $trans->no_update[ $file ] = (object) [
                    'id'           => $file,
                    'slug'         => $slug,
                    'plugin'       => $file,
                    'new_version'  => $current,
                    'url'          => $meta->homepage ?? '',
                    'package'      => '',
                    'icons'        => (array) ( $meta->icons ?? [] ),
                    'banners'      => (array) ( $meta->banners ?? [] ),
                    'tested'       => $meta->tested ?? '',
                    'requires'     => $meta->requires ?? $meta->min_wp_version ?? '',
                    'requires_php' => $meta->requires_php ?? '',
                    'compatibility'=> new \stdClass(),
                ];
                return $trans;
            }

            //  Inject update data
            $this->log( "Injecting plugin update '{$slug}' → v{$meta->version}" );
            $trans->response[ $file ] = (object) [
                'id'           => $file,
                'name'         => $c['name'],
                'slug'         => $slug,
                'plugin'       => $file,
                'new_version'  => $meta->version ?? $c['version'],
                'package'      => $meta->download_url ?? '',
                'url'          => $meta->homepage ?? '',
                'tested'       => $meta->tested ?? '',
                'requires'     => $meta->requires ?? $meta->min_wp_version ?? '',
                'requires_php' => $meta->requires_php ?? '',
                'sections'     => (array) ( $meta->sections ?? [] ),
                'icons'        => (array) ( $meta->icons ?? [] ),
                'banners'      => (array) ( $meta->banners ?? [] ),
                'compatibility'=> new \stdClass(),
            ];

            unset( $trans->no_update[ $file ] );
            return $trans;
        }

    public function theme_update( $trans ) {
        if ( ! is_object( $trans ) || ! isset( $trans->checked ) || ! is_array( $trans->checked ) ) {
            return $trans;
        }

        $c        = $this->config;
        $slug     = $c['real_slug'] ?? $c['slug'];        // WP expects real theme folder slug
        $cache_id = 'rup_' . $c['slug'];                  // Transient key for metadata
        $error_key = $cache_id . '_error';                // Transient key for error flag
        $current  = $trans->checked[ $slug ] ?? wp_get_theme( $slug )->get( 'Version' );

        $meta = get_transient( $cache_id );

        //  Skip if last fetch failed
        if ( false === $meta && get_transient( $error_key ) ) {
            $this->log( "Skipping theme update check for '{$c['slug']}' — previous error cached" );
            return $trans;
        }

        // If metadata is missing, try to fetch it (GitHub or private server)
        if ( false === $meta ) {
            if ( isset( $c['server'] ) && strpos( $c['server'], 'github.com' ) !== false ) {
                $repo_url  = rtrim( $c['server'], '/' );
                $cache_key = 'urup_github_release_' . md5( $repo_url );
                $release   = get_transient( $cache_key );

                if ( false === $release ) {
                    $api_url = str_replace( 'github.com', 'api.github.com/repos', $repo_url ) . '/releases/latest';
                    $token   = apply_filters( 'uupd/github_token_override', $c['github_token'] ?? '', $c['slug'] );

                    $headers = [ 'Accept' => 'application/vnd.github.v3+json' ];
                    if ( $token ) {
                        $headers['Authorization'] = 'token ' . $token;
                    }

                    $this->log( " GitHub fetch: $api_url" );
                    $response = wp_remote_get( $api_url, [ 'headers' => $headers ] );

                    if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
                        $release = json_decode( wp_remote_retrieve_body( $response ) );
                        $ttl = apply_filters( 'urup_fetch_remote_error_ttl', 6 * hoUR_IN_SECONDS, $slug );
                        set_transient( $error_key, time(), $ttl );

                    } else {
                        $this->log( 'GitHub API fetch failed — caching error state' );
                        set_transient( $error_key, time(), 6 * HOUR_IN_SECONDS );
                        do_action( 'urup_metadata_fetch_failed', [ 'slug' => $c['slug'], 'server' => $repo_url, 'message' => 'GitHub fetch failed' ] );
                        return $trans;
                    }
                }

                if ( isset( $release->tag_name ) ) {
                    $meta = (object) [
                        'version'      => ltrim( $release->tag_name, 'v' ),
                        'download_url' => $release->zipball_url,
                        'homepage'     => $release->html_url ?? $repo_url,
                        'sections'     => [ 'changelog' => $release->body ?? '' ],
                    ];
                } else {
                    $meta = (object) [
                        'version'      => $c['version'],
                        'download_url' => '',
                        'homepage'     => $repo_url,
                        'sections'     => [ 'changelog' => '' ],
                    ];
                }

                set_transient( $cache_id, $meta, apply_filters( 'urup_success_cache_ttl', 6 * HOUR_IN_SECONDS, $slug ) );
                delete_transient( $error_key );

            } else {
                $this->fetch_remote(); // will handle error caching internally
                $meta = get_transient( $cache_id );
            }
        }

        // Build base info used for both "no update" and "update available"
        $base_info = [
            'theme'        => $slug,
            'url'          => $meta->homepage ?? '',
            'requires'     => $meta->requires ?? '',
            'requires_php' => $meta->requires_php ?? '',
            'screenshot'   => $meta->screenshot ?? ''
        ];

        // Check if update is needed
        if ( ! $meta || version_compare( $meta->version ?? '0.0.0', $current, '<=' ) ) {
            $this->log( " Theme '{$c['slug']}' is up to date (v$current)" );
            $trans->no_update[ $slug ] = (object) array_merge( $base_info, [
                'new_version' => $current,
                'package'     => ''
            ] );
            return $trans;
        }

        $this->log( " Injecting theme update '{$c['slug']}' → v{$meta->version}'" );
        $trans->response[ $slug ] = array_merge( $base_info, [
            'new_version' => $meta->version ?? $current,
            'package'     => $meta->download_url ?? ''
        ] );

        unset( $trans->no_update[ $slug ] );
        return $trans;
    }


        /** Provide plugin information for the details popup. */
        public function plugin_info( $res, $action, $args ) {
            $c = $this->config;
            if ( 'plugin_information' !== $action || $args->slug !== $c['slug'] ) {
                return $res;
            }

            $meta = get_transient( 'rup_' . $c['slug'] );
            if ( ! $meta ) {
                return $res;
            }

            // Build sections array (description, installation, faq, screenshots, changelog…)
            $sections = [];
            if ( isset( $meta->sections ) ) {
                foreach ( (array) $meta->sections as $key => $content ) {
                    $sections[ $key ] = $content;
                }
            }

            return (object) [
                'name'            => $c['name'],
                'title'           => $c['name'],               // Popup title
                'slug'            => $c['slug'],
                'version'         => $meta->version        ?? '',
                'author'          => $meta->author         ?? '',
                'author_homepage' => $meta->author_homepage ?? '',
                'requires'        => $meta->requires       ?? $meta->min_wp_version ?? '',
                'tested'          => $meta->tested         ?? '',
                'requires_php'    => $meta->requires_php   ?? '',   // “Requires PHP: x.x or higher”
                'last_updated'    => $meta->last_updated   ?? '',
                'download_link'   => $meta->download_url   ?? '',
                'homepage'        => $meta->homepage       ?? '',
                'sections'        => $sections,
                'icons'           => isset( $meta->icons )   ? (array) $meta->icons   : [],
                'banners'         => isset( $meta->banners ) ? (array) $meta->banners : [],
                'screenshots'     => isset( $meta->screenshots ) 
                                       ? (array) $meta->screenshots 
                                       : [],
            ];
        }
        

        /** Provide theme information for the details popup. */
        public function theme_info( $res, $action, $args ) {
            $c = $this->config;
            $slug = $c['real_slug'] ?? $c['slug'];
            
            if ( 'theme_information' !== $action || $args->slug !== $slug ) {
                return $res;
            }

            $meta = get_transient( 'rup_' . $c['slug'] );
            if ( ! $meta ) {
                return $res;
            }
            // Safely extract changelog HTML
            if ( isset( $meta->changelog_html ) ) {
                $changelog = $meta->changelog_html;
            } elseif ( isset( $meta->sections ) ) {
                if ( is_array( $meta->sections ) ) {
                    $changelog = $meta->sections['changelog'] ?? '';
                } elseif ( is_object( $meta->sections ) ) {
                    $changelog = $meta->sections->changelog ?? '';
                } else {
                    $changelog = '';
                }
            } else {
                $changelog = '';
            }

            return (object) [
                'name'          => $c['name'],
                'slug'          => $c['real_slug'] ?? $c['slug'],
                'version'       => $meta->version ?? '',
                'tested'        => $meta->tested ?? '',
                'requires'      => $meta->min_wp_version ?? '',
                'sections'      => [ 'changelog' => $changelog ],
                'download_link' => $meta->download_url ?? '',
                'icons'         => isset( $meta->icons )   ? (array) $meta->icons   : [],
                'banners'       => isset( $meta->banners ) ? (array) $meta->banners : [],
            ];
        }

        /** Optional debug logger. */
        private function log( $msg ) {
            if ( apply_filters( 'updater_enable_debug', false ) ) {
                error_log( "[Updater] {$msg}" );
            }
        }

        /**
         * NEW STATIC HELPER: register everything (was the global function before).
         *
         * @param array $config  Same structure you passed to the old urup_register_updater_and_manual_check().
         */
        public static function register( array $config ) {
            // 1) Instantiate the updater class:
            new self( $config );

            // 2) Add the “Check for updates” link under the plugin row:
            $our_file   = $config['plugin_file'] ?? null;
            $slug       = $config['slug'];
            $textdomain = ! empty( $config['textdomain'] ) ? $config['textdomain'] : $slug;
            // Only register plugin row meta for plugins, not themes
            if ( $our_file ) {
                add_filter(
                    'plugin_row_meta',
                    function( array $links, string $file, array $plugin_data ) use ( $our_file, $slug, $textdomain ) {
                        if ( $file === $our_file ) {
                            $nonce     = wp_create_nonce( 'urup_manual_check_' . $slug );
                            $check_url = admin_url( sprintf(
                                'admin.php?action=urup_manual_check&slug=%s&_wpnonce=%s',
                                rawurlencode( $slug ),
                                $nonce
                            ) );

                            $links[] = sprintf(
                                '<a href="%s">%s</a>',
                                esc_url( $check_url ),
                                esc_html__( 'Check for updates', $textdomain )
                            );
                        }
                        return $links;
                    },
                    10,
                    3
                );
            }


            // 3) Hook up the manual‐check listener:
            add_action( 'admin_action_urup_manual_check', function() use ( $slug, $config ) {
            // 1) Grab the requested slug and normalize it.
            $request_slug = isset( $_REQUEST['slug'] ) ? sanitize_key( wp_unslash( $_REQUEST['slug'] ) ) : '';

            // 2) If the incoming 'slug' doesn’t match this plugin’s slug, bail out early:
            if ( $request_slug !== $slug ) {
                return; 
            }

            // 3) Only users who can update plugins/themes should proceed.
            if ( ! current_user_can( 'update_plugins' ) && ! current_user_can( 'update_themes' ) ) {
                wp_die( __( 'Cheatin’ uh?' ) );
            }

            // 4) Verify the nonce for this slug.
            $nonce     = isset( $_REQUEST['_wpnonce'] ) ? wp_unslash( $_REQUEST['_wpnonce'] ) : '';
            $checkname = 'urup_manual_check_' . $slug;
            if ( ! wp_verify_nonce( $nonce, $checkname ) ) {
                wp_die( __( 'Security check failed.' ) );
            }

            // 5) It’s our plugin’s “manual check,” so clear the transient and force WP to fetch again.
            delete_transient( 'rup_' . $slug );

            //ALSO clear GitHub release cache if using GitHub
            if ( isset( $config['server'] ) && strpos( $config['server'], 'github.com' ) !== false ) {
                $repo_url  = rtrim( $config['server'], '/' );
                $gh_key    = 'urup_github_release_' . md5( $repo_url );
                delete_transient( $gh_key );
            }

            if ( ! empty( $config['plugin_file'] ) ) {
                wp_update_plugins();
                $redirect = wp_get_referer() ?: admin_url( 'plugins.php' );
            } else {
                wp_update_themes();
                $redirect = wp_get_referer() ?: admin_url( 'themes.php' );
            }

            wp_safe_redirect( $redirect );
            exit;
        } );

        }
    }
}
