=== Cachelume ===
Contributors: emily50
Donate link: https://samratemily.netlify.app/
Tags: cache, performance, speed, optimization, minify
Requires at least: 5.0
Tested up to: 7.1
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A powerful and lightweight caching plugin for WordPress that improves website performance through page caching and HTML/CSS/JS minification.

== Description ==

**Cachelume** is a simple yet powerful caching solution designed to dramatically improve your WordPress website's loading speed. By caching your pages as static HTML files and optionally minifying your code, visitors experience lightning-fast page loads.

= Key Features =

* **Page Caching** - Stores rendered pages as static HTML files for instant delivery
* **HTML Minification** - Removes unnecessary whitespace and comments from HTML
* **CSS Minification** - Minifies inline CSS styles for smaller file sizes
* **JavaScript Minification** - Safely trims whitespace in inline JavaScript
* **Logged-in User Cache** - Optional caching for authenticated users
* **Cache Expiry Control** - Set custom cache lifetime from 1 hour to 1 week
* **Page Exclusions** - Exclude specific URLs from caching (supports wildcards)
* **Cookie Exclusions** - Skip caching when specific cookies are present
* **One-Click Cache Clear** - Clear all cache instantly from admin bar or settings
* **WooCommerce Compatible** - Pre-configured exclusions for cart, checkout, and account pages
* **Auto-Clear Cache** - Automatically clears cache when content or comments change

= Why Choose Cachelume? =

1. **Lightweight** - No bloated features, just essential caching that works
2. **Easy Setup** - Works out of the box with sensible defaults
3. **Beautiful Admin UI** - Modern, intuitive settings interface
4. **Developer Friendly** - Clean, well-documented code

= Perfect For =

* Blogs and personal websites
* Business and portfolio sites
* WooCommerce stores (with proper exclusions)
* High-traffic websites
* Shared hosting environments

== Installation ==

= Automatic Installation =

1. Go to Plugins > Add New in your WordPress admin
2. Search for "Cachelume"
3. Click "Install Now" and then "Activate"
4. Go to Cachelume > Settings to configure

= Manual Installation =

1. Download the plugin zip file
2. Go to Plugins > Add New > Upload Plugin
3. Upload the zip file and click "Install Now"
4. Activate the plugin
5. Go to Cachelume > Settings to configure

= FTP Installation =

1. Download and extract the plugin zip file
2. Upload the `cachelume` folder to `/wp-content/plugins/`
3. Activate the plugin through the Plugins menu
4. Go to Cachelume > Settings to configure

== Frequently Asked Questions ==

= Does this plugin work with WooCommerce? =

Yes! The plugin comes with pre-configured exclusions for WooCommerce cart, checkout, and my-account pages. It also excludes pages when WooCommerce cart cookies are present.

= Will caching break my dynamic content? =

By default, the plugin excludes logged-in users from caching. If you have specific pages with dynamic content, add them to the "Exclude Pages" list in settings.

= How do I clear the cache? =

You can clear the cache in several ways:
1. Click "Clear All Cache" button on the settings page
2. Click "Cache > Clear Cache" in the admin bar
3. The cache automatically clears when you update posts or pages

= Does this work with other caching plugins? =

We recommend using only one page caching plugin at a time to avoid conflicts. Disable other caching plugins before activating Cachelume.

= Is the minification safe? =

The minification is designed to be safe and only removes unnecessary whitespace and comments. It preserves content in `<pre>`, `<code>`, `<textarea>`, `<script>`, and `<style>` tags. However, if you experience any issues, you can disable minification individually for HTML, CSS, or JS.

= How do I exclude a page from caching? =

Go to Cachelume > Settings, find the "Exclude Pages" field, and add the URL path (one per line). For example:
* `/contact/` - Excludes the contact page
* `/api/*` - Excludes all URLs starting with /api/

= Does this plugin support multisite? =

The plugin works on multisite installations, but each site needs to be configured individually.

= How can I verify the cache is working? =

View the page source of a cached page. At the bottom, you'll see a comment like:
`<!-- Cached by Cachelume on 2024-01-15 10:30:00 -->`

You can also check the response headers for `X-Cachelume-Cache: HIT`.

== Screenshots ==

1. Settings page with cache statistics and configuration options
2. Clear cache page with one-click cache clearing
3. Admin bar quick access to cache settings and clear function
4. Beautiful toggle switches for easy configuration
5. Cache statistics showing cached pages and total size

== Changelog ==

= 1.0.0 =
* Initial release
* Page caching with static HTML files
* HTML, CSS, and JavaScript minification
* Configurable cache expiry (1 hour to 1 week)
* Exclude pages by URL pattern
* Exclude by cookies
* Toggle caching for logged-in users
* WooCommerce compatibility
* Auto-clear cache on content updates
* Admin bar integration
* Modern, responsive admin UI

== Upgrade Notice ==

= 1.0.0 =
Initial release of Cachelume. Install to improve your website's performance!

== External Services ==

This plugin does **not** connect to any external APIs or third-party services. All caching is performed locally on your server — rendered pages are saved as static HTML files in the `wp-content/cache/cachelume/` directory and served directly from there.

**WordPress REST API discovery links in cached content**

WordPress core automatically injects a `<link rel="https://api.w.org/">` tag and a link to `xmlrpc.php` into every page's `<head>` section. When this plugin caches a page, those links are stored as part of the cached HTML exactly as WordPress generated them. This plugin does not itself send any data to `api.w.org` or any other remote host; it simply preserves whatever markup WordPress core has already rendered.

No user data is collected, transmitted, or processed by this plugin. If you need information about WordPress core's own REST API or XML-RPC endpoints, please refer to:

* WordPress.org Privacy Policy: https://wordpress.org/about/privacy/
* WordPress.org Terms of Service: https://wordpress.org/about/license/

== Additional Info ==

= Minimum Requirements =

* WordPress 5.0 or higher
* PHP 7.4 or higher

= Support =

For support, please visit [our website](https://samrat-personal-portfolio.netlify.app/) or create a support topic on the WordPress.org plugin page.

= Contributing =

Contributions are welcome! Please feel free to submit a Pull Request on our GitHub repository.
