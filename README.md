# Cachelume

[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPLv2-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Version](https://img.shields.io/badge/Version-1.0.0-orange.svg)](https://github.com/samrathossen/cachelume)

A powerful and lightweight caching plugin for WordPress that dramatically improves website performance through intelligent page caching and code minification.

![Cachelume Banner](assets/images/banner-772x250.png)

## ✨ Features

### 🚀 Page Caching
- Stores rendered pages as static HTML files
- Serves cached content early, before the theme and database queries run
- Configurable cache expiry from 1 hour to 1 week
- Automatic cache invalidation on content updates
- Daily background cleanup of expired cache files

### 📦 Minification
- **HTML Minification** - Removes unnecessary whitespace and comments
- **CSS Minification** - Minifies inline `<style>` blocks
- **JavaScript Minification** - Trims whitespace in inline `<script>` blocks (line breaks and comments are kept so scripts can't break)
- Safe minification that preserves `<pre>`, `<code>`, and `<textarea>` content

### ⚙️ Smart Exclusions
- Exclude specific URLs with wildcard support
- Cookie-based exclusions (great for WooCommerce)
- Automatic exclusions for:
  - Admin pages
  - AJAX requests
  - REST API requests
  - Search results
  - 404 pages
  - Preview pages
  - Password-protected posts
  - Visitors who have commented or unlocked a password-protected post
  - Pages that define `DONOTCACHEPAGE`
  - URLs with query parameters other than WordPress core query vars (tracking parameters such as `utm_*` and `fbclid` are ignored)

### 🛒 WooCommerce Compatible
Pre-configured exclusions for:
- `/cart/`
- `/checkout/`
- `/my-account/`
- Cart cookies (prevents caching when items in cart)

### 🎨 Modern Admin Interface
- Beautiful, responsive settings page
- Real-time cache statistics
- One-click cache clearing
- Admin bar integration for quick access

## 📋 Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher

## 🔧 Installation

### From WordPress Admin

1. Go to **Plugins → Add New**
2. Search for "Cachelume"
3. Click **Install Now** then **Activate**
4. Navigate to **Cachelume → Settings** to configure

### Manual Installation

1. Download the latest release
2. Upload the `cachelume` folder to `/wp-content/plugins/`
3. Activate through the **Plugins** menu
4. Configure at **Cachelume → Settings**

## ⚡ Quick Start

The plugin works out of the box with sensible defaults:

| Setting | Default |
|---------|---------|
| Page Cache | ✅ Enabled |
| Cache for Logged-in Users | ❌ Disabled |
| Cache Expiry | 24 hours |
| HTML Minification | ❌ Disabled |
| CSS Minification | ❌ Disabled |
| JS Minification | ❌ Disabled |

For most websites, simply activate and you're done!

## 🎯 Configuration

### Page Cache Settings

| Option | Description |
|--------|-------------|
| **Enable Page Cache** | Toggle page caching on/off |
| **Cache for Logged-in Users** | Enable caching for authenticated users (not recommended for dynamic content) |
| **Cache Expiry Time** | How long to keep cached pages (1 hour - 1 week) |

### Minification Settings

| Option | Description |
|--------|-------------|
| **Minify HTML** | Remove whitespace and comments from HTML |
| **Minify CSS** | Minify inline CSS styles |
| **Minify JavaScript** | Minify inline JavaScript code |

### Cache Exclusions

**Exclude Pages** - Enter URL paths to exclude (one per line):
```
/cart/
/checkout/
/my-account/
/api/*
/contact/
```

**Exclude Cookies** - Skip caching when these cookies exist:
```
woocommerce_cart_hash
woocommerce_items_in_cart
custom_session_cookie
```

## 🔍 Verifying Cache is Working

### Method 1: View Page Source
Look for this comment at the bottom of your HTML (timestamp is in UTC):
```html
<!-- Cached by Cachelume on 2024-01-15 10:30:00 -->
```

### Method 2: Check Response Headers
Cached responses include these headers (timestamp in UTC):
```
X-Cachelume-Cache: HIT
X-Cachelume-Cache-Time: 2024-01-15 10:30:00
```

### Method 3: Admin Dashboard
Visit **Cachelume → Settings** to see:
- Number of cached pages
- Total cache size
- Cache status (Active/Inactive)

## 🗑️ Clearing Cache

### Manual Clear
1. **Settings Page** - Click "Clear All Cache" button
2. **Admin Bar** - Click "Cache → Clear Cache"
3. **Clear Cache Page** - Visit Cachelume → Clear Cache

### Automatic Clear
Cache automatically clears when:
- Posts/pages are updated, unpublished, trashed or deleted
- Comments are approved, edited, unapproved or removed
- Plugin settings are changed
- Theme is changed
- Plugins are activated/deactivated
- WordPress core is updated
- WooCommerce stock changes

## 📁 File Structure

```
cachelume/
├── assets/
│   ├── css/
│   │   └── admin.css           # Admin styles
│   ├── images/
│   │   ├── banner-772x250.png  # WordPress.org banner
│   │   └── icon-256x256.png    # WordPress.org icon
│   └── js/
│       └── admin.js            # Admin JavaScript
├── includes/
│   ├── AdminMenu.php           # Admin menu & settings
│   └── CacheHandler.php        # Core caching logic
├── index.php                   # Prevents directory listing
├── README.md                   # This file
├── readme.txt                  # WordPress.org readme
├── uninstall.php               # Removes settings and cache on delete
└── cachelume.php               # Main plugin file
```

Cached pages are stored outside the plugin in `wp-content/cache/cachelume/`.

## 🐛 Troubleshooting

### Pages not being cached

1. Check if page caching is enabled in settings
2. Ensure you're not logged in (unless "Cache for Logged-in Users" is enabled)
3. Check if the page URL is in exclusion list
4. Verify no excluded cookies are present
5. Clear cache and try again

### Styling/Layout issues

1. Disable HTML minification
2. Disable CSS minification
3. Clear cache after changes
4. Check for JavaScript errors in console

### Cache not clearing

1. Check file permissions on the `wp-content/cache/cachelume/` directory
2. Ensure WordPress has write access
3. Try clearing manually via FTP/file manager

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📄 License

This project is licensed under the GPLv2 or later - see the [GNU GPL v2](https://www.gnu.org/licenses/gpl-2.0.html) for details.

## 👨‍💻 Author

**Samrat Hossen**

- Website: [samratemily.netlify.app](https://samratemily.netlify.app/)
- GitHub: [@samrathossen](https://github.com/samrathossen)

## 🙏 Acknowledgments

- WordPress Plugin Development Team
- The WordPress community for continuous support and feedback

---

⭐ If you find this plugin helpful, please consider giving it a star on GitHub!
