<?php

/**
 * Cache Handler Class for Cachelume
 *
 * @package Cachelume
 */

if (!defined('ABSPATH')) {
    exit;
}

class Cachelume_Cache_Handler {

    /**
     * Cache settings
     *
     * @var array
     */
    private $settings;

    /**
     * Whether we should attempt caching
     *
     * @var bool
     */
    private $can_cache = false;

    /**
     * Tracking query parameters that don't change page output. They are
     * stripped from the cache key so tracked links share the normal cached page.
     *
     * @var array
     */
    private $ignored_query_params = array(
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_id',
        'fbclid', 'gclid', 'gbraid', 'wbraid', 'msclkid', 'dclid',
        'mc_cid', 'mc_eid', '_ga', '_gl',
    );

    /**
     * Constructor
     */
    public function __construct() {
        $this->settings = $this->get_settings();
        
        // Only add cache hooks if page cache is enabled
        if ($this->settings['enable_page_cache']) {
            // Try to serve cached content early (on plugins_loaded, before the theme and query run)
            $this->maybe_serve_cached_content();
            
            // Start output buffering after template is loaded
            add_action('template_redirect', array($this, 'start_cache'), 0);
        }

        // Auto-clear cache hooks
        add_action('save_post', array($this, 'clear_cache_on_update'));

        // Clear the post's current URL before it changes. Unpublishing or changing
        // the slug changes the permalink, and trashing appends "__trashed" to the
        // slug, so clearing only after the update would miss the old URL.
        add_action('pre_post_update', array($this, 'clear_cache_on_update'));
        add_action('wp_trash_post', array($this, 'clear_cache_on_update'));
        add_action('before_delete_post', array($this, 'clear_cache_on_update'));
        add_action('switch_theme', array($this, 'clear_all_cache'));
        add_action('activated_plugin', array($this, 'clear_all_cache'));
        add_action('deactivated_plugin', array($this, 'clear_all_cache'));
        add_action('upgrader_process_complete', array($this, 'clear_all_cache'));
        
        // WooCommerce specific hooks — these pass WC_Product objects, so use a dedicated handler.
        add_action('woocommerce_product_set_stock', array($this, 'clear_cache_on_wc_stock_update'));
        add_action('woocommerce_variation_set_stock', array($this, 'clear_cache_on_wc_stock_update'));

        // Comment hooks — clear the post's cache when its visible comments change.
        add_action('comment_post', array($this, 'clear_cache_on_new_comment'), 10, 2);
        add_action('edit_comment', array($this, 'clear_cache_on_comment_edit'));
        add_action('transition_comment_status', array($this, 'clear_cache_on_comment_status'), 10, 3);

        // Scheduled cleanup of expired cache files
        add_action('cachelume_purge_expired', array($this, 'purge_expired_cache'));

        // Settings changes (minification, exclusions, etc.) affect cached output,
        // so start fresh. Only fires when the saved value actually changed.
        add_action('update_option_cachelume_settings', array($this, 'clear_all_cache'));
    }

    /**
     * Get cache settings
     *
     * @return array
     */
    private function get_settings() {
        $defaults = cachelume_get_default_settings();

        $options = get_option('cachelume_settings', $defaults);
        return wp_parse_args($options, $defaults);
    }

    /**
     * Check basic conditions (before the main query runs)
     *
     * @return bool
     */
    private function can_serve_cache_early() {
        // Don't cache admin pages
        if (is_admin()) {
            return false;
        }

        // Don't cache AJAX requests
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return false;
        }

        // Don't cache cron
        if (defined('DOING_CRON') && DOING_CRON) {
            return false;
        }

        // Don't cache WP CLI
        if (defined('WP_CLI') && WP_CLI) {
            return false;
        }

        // Don't cache POST requests
        $request_method = '';
        if (isset($_SERVER['REQUEST_METHOD'])) {
            $request_method = sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD']));
        }

        if ($request_method !== 'GET') {
            return false;
        }

        // Check excluded pages (simple URL check)
        if ($this->is_excluded_page()) {
            return false;
        }

        // Don't serve cache for URLs with unrecognized query parameters
        if (false === $this->get_normalized_request_uri()) {
            return false;
        }

        // Check excluded cookies
        if ($this->has_excluded_cookie()) {
            return false;
        }

        // Don't serve cache to visitors who may see personalized content
        if ($this->has_private_cookie()) {
            return false;
        }

        // Don't serve cache if logged in (check cookie)
        if (!$this->settings['cache_logged_users']) {
            foreach (array_keys($_COOKIE) as $key) {
                if (strpos($key, 'wordpress_logged_in_') === 0) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Check if current page should be cached (full check after WP loads)
     *
     * @return bool
     */
    private function should_cache() {
        // Don't cache admin pages
        if (is_admin()) {
            return false;
        }

        // Don't cache AJAX requests
        if (wp_doing_ajax()) {
            return false;
        }

        // Don't cache REST API requests
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return false;
        }

        // Don't cache POST requests
        $request_method = '';
        if (isset($_SERVER['REQUEST_METHOD'])) {
            $request_method = sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD']));
        }

        if ($request_method !== 'GET') {
            return false;
        }

        // Check logged-in user setting
        if (is_user_logged_in() && !$this->settings['cache_logged_users']) {
            return false;
        }

        // Check excluded pages
        if ($this->is_excluded_page()) {
            return false;
        }

        // Don't cache URLs with unrecognized query parameters. Arbitrary query
        // strings would otherwise create a new cache file per unique URL.
        if (false === $this->get_normalized_request_uri()) {
            return false;
        }

        // Check excluded cookies
        if ($this->has_excluded_cookie()) {
            return false;
        }

        // Don't cache pages that may contain personalized content
        if ($this->has_private_cookie()) {
            return false;
        }

        // Don't cache search results
        if (function_exists('is_search') && is_search()) {
            return false;
        }

        // Don't cache 404 pages
        if (function_exists('is_404') && is_404()) {
            return false;
        }

        // Don't cache feed pages
        if (function_exists('is_feed') && is_feed()) {
            return false;
        }

        // Don't cache preview pages
        if (function_exists('is_preview') && is_preview()) {
            return false;
        }

        // Don't cache password protected posts
        if (function_exists('post_password_required') && post_password_required()) {
            return false;
        }

        return true;
    }

    /**
     * Check if current page is in excluded pages list
     *
     * @return bool
     */
    private function is_excluded_page() {
        $excluded_pages = $this->settings['exclude_pages'];
        if (empty($excluded_pages)) {
            return false;
        }

        $excluded = array_filter(array_map('trim', explode("\n", $excluded_pages)));
        $current_uri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '';

        foreach ($excluded as $pattern) {
            if (empty($pattern)) {
                continue;
            }
            
            // Check if pattern contains wildcard
            if (strpos($pattern, '*') !== false) {
                $regex = '#' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '#';
                if (preg_match($regex, $current_uri)) {
                    return true;
                }
            } else {
                if (strpos($current_uri, $pattern) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if any excluded cookie is present
     *
     * @return bool
     */
    private function has_excluded_cookie() {
        $excluded_cookies = $this->settings['exclude_cookies'];
        if (empty($excluded_cookies)) {
            return false;
        }

        $excluded = array_filter(array_map('trim', explode("\n", $excluded_cookies)));

        foreach ($excluded as $cookie_name) {
            if (!empty($_COOKIE[$cookie_name])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the visitor has a cookie that makes WordPress personalize the page.
     *
     * wp-postpass_*: visitor has unlocked a password-protected post, so
     * post_password_required() returns false and the unlocked content renders.
     * comment_author_*: WordPress pre-fills the comment form with the
     * commenter's name, email and URL.
     *
     * @return bool
     */
    private function has_private_cookie() {
        foreach (array_keys($_COOKIE) as $key) {
            if (strpos($key, 'wp-postpass_') === 0 || strpos($key, 'comment_author_') === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Try to serve cached content before the theme and main query run
     */
    private function maybe_serve_cached_content() {
        if (!$this->can_serve_cache_early()) {
            return;
        }

        $cache_file = $this->get_cache_file();

        if (!file_exists($cache_file)) {
            return;
        }

        // Check cache expiry
        $file_time = filemtime($cache_file);
        $expiry = intval($this->settings['cache_expiry']);

        if ((time() - $file_time) > $expiry) {
            // Cache expired, delete it
            // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
            @unlink($cache_file);
            return;
        }

        // Serve cached content
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        $content = @file_get_contents($cache_file);
        
        if (empty($content)) {
            return;
        }

        // A WordPress nonce may stop validating once it is older than half of
        // nonce_life (12 hours by default). Pages that embed nonces (forms, AJAX
        // actions) must not be served past that point, or they fail with
        // "The link you followed has expired".
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Reading WordPress core's own filter.
        $nonce_expiry = (int) (apply_filters('nonce_life', DAY_IN_SECONDS) / 2);
        if ($expiry > $nonce_expiry && (time() - $file_time) > $nonce_expiry && stripos($content, 'nonce') !== false) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
            @unlink($cache_file);
            return;
        }

        // Set appropriate headers
        header('Content-Type: text/html; charset=UTF-8');
        header('X-Cachelume-Cache: HIT');
        header('X-Cachelume-Cache-Time: ' . gmdate('Y-m-d H:i:s', $file_time));
        
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $content;
        exit;
    }

    /**
     * Start output buffering for cache.
     *
     * Opens an output buffer with end_cache() as its callback. PHP will
     * automatically invoke the callback — and close the buffer — when the
     * request ends, keeping the buffer lifecycle entirely self-contained
     * within this single ob_start() call.
     */
    public function start_cache() {
        // Final check if we should cache this page
        if (!$this->should_cache()) {
            return;
        }

        $this->can_cache = true;

        ob_start( array( $this, 'end_cache' ) );
    }

    /**
     * Process and cache the buffered output, then return it to the browser.
     *
     * Called automatically by PHP as the ob_start() callback when the buffer
     * is flushed at the end of the request. Whatever this method returns is
     * sent directly to the browser.
     *
     * @param string $content The buffered page output.
     * @return string The (possibly minified) page output.
     */
    public function end_cache( $content ) {
        if ( ! $this->can_cache || empty( $content ) ) {
            return $content;
        }

        $this->process_and_cache( $content );

        return $content;
    }

    /**
     * Process buffered output and save to cache file
     *
     * @param string $content The buffered output
     */
    private function process_and_cache($content) {
        // Don't cache empty content
        if (empty($content)) {
            return;
        }

        // Don't cache if it doesn't look like a complete HTML page
        if (strpos($content, '</html>') === false && strpos($content, '</HTML>') === false) {
            return;
        }

        // Don't cache error pages
        if (http_response_code() !== 200) {
            return;
        }

        // Respect the standard "don't cache this page" signal. WooCommerce, form
        // plugins and others define it while rendering, so check it here at the
        // end of the request rather than before output starts.
        if (defined('DONOTCACHEPAGE') && DONOTCACHEPAGE) {
            return;
        }

        // Apply minification if enabled
        $cached_content = $this->maybe_minify($content);

        // Add cache signature
        $cached_content .= $this->get_cache_signature();

        // Save the cache content to a file
        $this->save_cache($cached_content);
    }

    /**
     * Save content to cache file
     *
     * @param string $content Content to cache
     */
    private function save_cache($content) {
        $cache_file = $this->get_cache_file();
        $cache_dir = dirname($cache_file);

        if (!file_exists($cache_dir)) {
            wp_mkdir_p($cache_dir);
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        @file_put_contents($cache_file, $content, LOCK_EX);
    }

    /**
     * Apply minification to content
     *
     * @param string $content HTML content
     * @return string Minified content
     */
    private function maybe_minify($content) {
        if ($this->settings['minify_html']) {
            $content = $this->minify_html($content);
        }

        if ($this->settings['minify_css']) {
            $content = $this->minify_inline_css($content);
        }

        if ($this->settings['minify_js']) {
            $content = $this->minify_inline_js($content);
        }

        return $content;
    }

    /**
     * Minify HTML content
     *
     * @param string $html HTML content
     * @return string Minified HTML
     */
    private function minify_html($html) {
        // Don't minify if no HTML
        if (empty($html)) {
            return $html;
        }

        // Store pre/code/textarea/script/style content
        $protected = array();
        $html = preg_replace_callback(
            // The closing tag must match the opening one (\2), so <pre><code>…</code> more</pre>
            // is protected up to </pre> rather than the first </code>.
            '#(<(pre|code|textarea|script|style)\b[^>]*>)(.*?)(</\2>)#si',
            function ($matches) use (&$protected) {
                $key = '<!-- CACHELUME_PROTECTED_' . count($protected) . ' -->';
                $protected[$key] = $matches[0];
                return $key;
            },
            $html
        );

        // Remove HTML comments (except IE conditionals and protected)
        $html = preg_replace('/<!--(?!\s*(?:\[if [^\]]+]|<!|>|CACHELUME_PROTECTED))(?:(?!-->).)*-->/s', '', $html);
        
        // Remove whitespace between tags (be careful with inline elements)
        $html = preg_replace('/>\s+</', '> <', $html);
        
        // Remove multiple spaces (but keep at least one)
        $html = preg_replace('/\s{2,}/', ' ', $html);
        
        // Remove unnecessary whitespace around block elements
        $html = preg_replace('/\s*(<\/?(?:div|p|section|article|header|footer|nav|aside|main|ul|ol|li|h[1-6]|table|tr|td|th|thead|tbody|form)[^>]*>)\s*/i', '$1', $html);

        // Restore protected content
        $html = str_replace(array_keys($protected), array_values($protected), $html);

        return trim($html);
    }

    /**
     * Minify inline CSS
     *
     * @param string $html HTML content
     * @return string HTML with minified CSS
     */
    private function minify_inline_css($html) {
        return preg_replace_callback(
            '#<style[^>]*>(.*?)</style>#si',
            function ($matches) {
                $css = $matches[1];

                // Remove comments and protect strings and url() values in a single
                // left-to-right pass, so a quote inside a comment or a "/*" inside
                // a string can't be mistaken for the other.
                $protected = array();
                $css = preg_replace_callback(
                    '#/\*.*?\*/|"(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\'|url\(\s*[^)"\'\s]*\s*\)#is',
                    function ($token) use (&$protected) {
                        if (strpos($token[0], '/*') === 0) {
                            return '';
                        }
                        $key = '___CACHELUME_CSS_' . count($protected) . '___';
                        $protected[$key] = $token[0];
                        return $key;
                    },
                    $css
                );

                // Collapse whitespace
                $css = preg_replace('/\s+/', ' ', $css);

                // Remove spaces around characters where they never matter. "+" and
                // ":" are left alone: calc() requires spaces around "+", and a space
                // before ":" is a descendant selector (".menu :hover").
                $css = preg_replace('/\s*([;{},>])\s*/', '$1', $css);

                // Remove trailing semicolons before closing braces
                $css = preg_replace('/;}/', '}', $css);

                // Restore protected strings and url() values
                $css = strtr($css, $protected);
                
                // Get opening tag
                preg_match('#<style[^>]*>#i', $matches[0], $tag);
                
                return $tag[0] . trim($css) . '</style>';
            },
            $html
        );
    }

    /**
     * Minify inline JavaScript
     *
     * @param string $html HTML content
     * @return string HTML with minified JS
     */
    private function minify_inline_js($html) {
        return preg_replace_callback(
            '#<script([^>]*)>(.*?)</script>#si',
            function ($matches) {
                $attrs = $matches[1];
                $js = $matches[2];
                
                // Skip if has src attribute (external script) or empty
                if (preg_match('/\bsrc\s*=/i', $attrs) || empty(trim($js))) {
                    return $matches[0];
                }

                // Skip non-JavaScript scripts (JSON, HTML templates, etc.)
                if (preg_match('/\btype\s*=\s*["\']?([^"\'\s>]+)/i', $attrs, $type)
                    && !preg_match('#^(?:text/javascript|application/javascript|module)$#i', $type[1])) {
                    return $matches[0];
                }

                // Template literals and backslash-continued strings can span lines,
                // so their whitespace is part of a string value. Leave them untouched.
                if (strpos($js, '`') !== false || preg_match('/\\\\\r?\n/', $js)) {
                    return $matches[0];
                }

                // Trim each line and drop blank lines. Line breaks are kept because
                // JavaScript relies on them for automatic semicolon insertion.
                // Comments are kept because "//" and "/*" can't be told apart from
                // the same characters inside strings or regexes without a full parser.
                $lines = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $js)), 'strlen');

                return '<script' . $attrs . '>' . implode("\n", $lines) . '</script>';
            },
            $html
        );
    }

    /**
     * Get cache signature comment
     *
     * @return string
     */
    private function get_cache_signature() {
        return "\n<!-- Cached by Cachelume on " . gmdate('Y-m-d H:i:s') . " -->";
    }

    /**
     * Get cache file path
     *
     * @return string
     */
    private function get_cache_file() {
        $cache_dir = CACHELUME_CACHE_DIR;
        
        // Create a unique cache key based on URL and user state
        $cache_key = $this->get_cache_key();
        
        return $cache_dir . $cache_key . '.html';
    }

    /**
     * Get cache key for current request
     *
     * @return string
     */
    private function get_cache_key() {
        $uri = $this->get_normalized_request_uri();

        // Use the configured site host rather than the user-supplied HTTP_HOST header.
        // HTTP_HOST is fully attacker-controlled and using it directly would allow
        // cache pollution via forged Host headers (disk exhaustion attack).
        $host = (string) wp_parse_url(home_url(), PHP_URL_HOST);

        return md5($host . '|' . $uri) . $this->get_user_cache_suffix();
    }

    /**
     * Get the current request URI normalized for use in a cache key.
     *
     * @return string|false Normalized URI, or false if the request isn't cacheable.
     */
    private function get_normalized_request_uri() {
        $uri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '/';
        return $this->normalize_uri($uri);
    }

    /**
     * Normalize a URI (path plus optional query string) for use in a cache key.
     *
     * Tracking parameters are dropped. The remaining parameters must all be
     * WordPress core query vars (e.g. ?p=123 on sites with plain permalinks),
     * otherwise the URI isn't cacheable. Parameters are sorted so the same page
     * always maps to the same key.
     *
     * @param string $uri URI path with optional query string.
     * @return string|false Normalized URI, or false if it isn't cacheable.
     */
    private function normalize_uri($uri) {
        $parts = explode('?', $uri, 2);
        $path  = $parts[0];

        if (!isset($parts[1]) || '' === $parts[1]) {
            return $path;
        }

        parse_str($parts[1], $query);

        foreach ($this->ignored_query_params as $param) {
            unset($query[$param]);
        }

        if (array_diff(array_keys($query), $this->get_core_query_vars())) {
            return false;
        }

        foreach ($query as $value) {
            if (!is_scalar($value)) {
                return false;
            }
        }

        if (empty($query)) {
            return $path;
        }

        ksort($query);
        return esc_url_raw($path . '?' . http_build_query($query));
    }

    /**
     * Get WordPress core's public query vars.
     *
     * Uses the core defaults rather than the filtered list so the result is
     * identical when serving early (plugins_loaded) and when saving the cache.
     *
     * @return array
     */
    private function get_core_query_vars() {
        static $vars = null;

        if (null === $vars) {
            $wp   = new WP();
            $vars = $wp->public_query_vars;
        }

        return $vars;
    }

    /**
     * Get the per-user cache file suffix for logged-in users.
     *
     * Each logged-in user gets their own cache variant so one user's admin bar,
     * name and role-specific content is never served to another user. The
     * suffix is a salted hash so cache filenames can't be guessed from a user ID.
     *
     * @return string Empty string for logged-out visitors.
     */
    private function get_user_cache_suffix() {
        if (!$this->settings['cache_logged_users']) {
            return '';
        }

        $user_id = wp_validate_auth_cookie('', 'logged_in');
        if (!$user_id) {
            return '';
        }

        return '-' . wp_hash('cachelume_user_' . $user_id);
    }

    /**
     * Delete the cache files for a URL, including every logged-in user's variant.
     *
     * @param string $cache_key Base cache key (md5 of host|uri).
     */
    private function delete_cache_files($cache_key) {
        $files = glob(CACHELUME_CACHE_DIR . $cache_key . '*.html');
        if ($files) {
            foreach ($files as $file) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
                @unlink($file);
            }
        }
    }

    /**
     * Clear cache when WooCommerce stock changes.
     *
     * woocommerce_product_set_stock and woocommerce_variation_set_stock both pass
     * a WC_Product (or WC_Product_Variation) object rather than a plain integer ID.
     *
     * @param object $product WC_Product or WC_Product_Variation instance.
     */
    public function clear_cache_on_wc_stock_update($product) {
        if (is_a($product, 'WC_Product')) {
            $this->clear_cache_on_update($product->get_id());
        }
    }

    /**
     * Clear cache when content is updated
     *
     * @param int $post_id Post ID
     */
    public function clear_cache_on_update($post_id = null) {
        // Don't run on autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Clear specific post cache if possible
        if ($post_id) {
            $post = get_post($post_id);
            
            // Skip revisions and auto-drafts
            if (!$post || $post->post_status === 'auto-draft' || $post->post_type === 'revision') {
                return;
            }

            $permalink = get_permalink($post_id);
            if ($permalink) {
                $parsed = wp_parse_url($permalink);
                $uri = isset($parsed['path']) ? $parsed['path'] : '/';
                $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';
                $host = isset($parsed['host']) ? $parsed['host'] : wp_parse_url(home_url(), PHP_URL_HOST);

                // Normalise the same way as get_cache_key() so the keys match.
                $normalized_uri = $this->normalize_uri(esc_url_raw($uri . $query));

                // Clear logged-out cache and every logged-in user's variant
                if (false !== $normalized_uri) {
                    $this->delete_cache_files(md5($host . '|' . $normalized_uri));
                }
            }
        }

        // Also clear homepage cache
        $home_url = home_url('/');
        $parsed = wp_parse_url($home_url);
        $host = isset($parsed['host']) ? $parsed['host'] : '';

        $this->delete_cache_files(md5($host . '|/'));
    }

    /**
     * Clear cache when a new comment is published.
     *
     * @param int        $comment_id       Comment ID.
     * @param int|string $comment_approved 1 if approved, 0 if pending, 'spam' if spam.
     */
    public function clear_cache_on_new_comment($comment_id, $comment_approved) {
        // Pending and spam comments aren't shown, so the page hasn't changed.
        if (1 !== (int) $comment_approved) {
            return;
        }

        $comment = get_comment($comment_id);
        if ($comment) {
            $this->clear_cache_on_update($comment->comment_post_ID);
        }
    }

    /**
     * Clear cache when a published comment is edited.
     *
     * @param int $comment_id Comment ID.
     */
    public function clear_cache_on_comment_edit($comment_id) {
        $comment = get_comment($comment_id);
        if ($comment && '1' === (string) $comment->comment_approved) {
            $this->clear_cache_on_update($comment->comment_post_ID);
        }
    }

    /**
     * Clear cache when a comment is approved, unapproved, spammed, trashed or deleted.
     *
     * @param string     $new_status New comment status.
     * @param string     $old_status Old comment status.
     * @param WP_Comment $comment    Comment object.
     */
    public function clear_cache_on_comment_status($new_status, $old_status, $comment) {
        // Only changes to or from "approved" affect what visitors see.
        if ($new_status === $old_status || ('approved' !== $new_status && 'approved' !== $old_status)) {
            return;
        }

        $this->clear_cache_on_update($comment->comment_post_ID);
    }

    /**
     * Delete cache files older than the configured cache expiry.
     *
     * Runs daily via WP-Cron. Expired files are otherwise only deleted when
     * the same URL is requested again, so without this they build up.
     */
    public function purge_expired_cache() {
        $files = glob(CACHELUME_CACHE_DIR . '*.html');
        if (!$files) {
            return;
        }

        $expiry = intval($this->settings['cache_expiry']);
        $now    = time();

        foreach ($files as $file) {
            if (is_file($file) && ($now - filemtime($file)) > $expiry) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
                @unlink($file);
            }
        }
    }

    /**
     * Clear all cache files
     */
    public function clear_all_cache() {
        $cache_dir = CACHELUME_CACHE_DIR;
        
        if (!file_exists($cache_dir)) {
            return;
        }

        $files = glob($cache_dir . '*.html');
        if ($files) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
                    @unlink($file);
                }
            }
        }
    }
}
