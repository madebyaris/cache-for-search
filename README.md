# Redis / Disk cache for Search (full cache search) - WordPress Plugin

[![License: GPL v2 or later](https://img.shields.io/badge/License-GPL%20v2%20or%20later-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

A high-performance WordPress plugin that enhances search functionality by implementing caching via Redis or Disk. This plugin significantly improves search performance and reduces database load by caching search results.

## Screenshots
![Screenshot 1](/media/screenshoot.png)

## 💝 Support & Donations
If you find this plugin useful, please consider supporting me by making a donation. Your support helps me maintain and improve the plugin.

[![PayPal](https://img.shields.io/badge/Donate-PayPal-blue.svg)](https://www.paypal.com/paypalme/airs) [![Trakteer](https://img.shields.io/badge/Donate-Trakteer-red.svg)](https://trakteer.id/madebyaris/tip)

## 👨‍💻 Hire Me
✉️ [arissetia.m@gmail.com](arissetia.m@gmail.com)
🌐 [madebyaris.com](https://madebyaris.com)

## Features

- 🚀 Redis-powered search result caching
- 🧠 Smart two-layer caching system
- 📊 Cache statistics tracking (hits/misses)
- 🔄 Automatic cache invalidation on post updates
- ⚙️ Smart caching strategies with adaptive algorithms
- 🛠️ Configurable Redis and disk cache settings
- 🎯 Admin interface for easy configuration
- ⚡ Fallback to disk cache when Redis is unavailable

## Requirements

- PHP 7.1 or higher
- WordPress 5.0 or higher
- Redis server (optional)
- PHP Redis extension (Only if you want to use Redis)

## Installation

1. Download the plugin from [GitHub](https://github.com/madebyaris/cache-for-search)
2. Upload the plugin files to the `/wp-content/plugins/redis-for-search` directory
3. Activate the plugin through the 'Plugins' screen in WordPress
4. Configure the plugin settings under 'Settings > Redis for Search'

## Configuration

1. Navigate to 'Settings > Redis for Search' in your WordPress admin panel
2. Configure your caching settings:
   - Redis connection:
     - Host (default: localhost)
     - Port (default: 6379)
   - Cache type (redis, disk, or both)
   - Smart cache options:
     - Cache lifetime
     - Minimum hit count for persistence
     - Adaptive caching threshold
3. Optional: Enable auto-revalidation for automatic cache updates

## Usage

Once configured, the plugin works automatically. It will:
- Cache search results using the smart two-layer system:
  - First layer: Redis for high-speed access
  - Second layer: Disk cache for persistence and fallback
- Intelligently manage cache storage based on search patterns
- Serve cached results for identical searches from the fastest available source
- Automatically invalidate cache when posts are updated
- Track cache performance statistics for both layers
- Adaptively optimize cache storage based on usage patterns

### WP-CLI Commands

The plugin provides several WP-CLI commands to manage the cache:

```bash
# Rebuild the search cache for all posts
wp redis-for-search rebuild

# Options:
#   --type=<type>    Cache type (redis or disk)
#   --batch=<size>   Batch size for processing (default: 100)
```

Example usage:
```bash
# Rebuild cache using Redis
wp redis-for-search rebuild --type=redis

# Rebuild cache using disk storage with batch size of 200
wp redis-for-search rebuild --type=disk --batch=200
```

Note: Make sure WP-CLI is installed and you're in your WordPress installation directory when running these commands.

## Contributing

Contributions are welcome! Feel free to:
1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## Author

**M Aris Setiawan**
- GitHub: [madebyaris](http://github.com/madebyaris)
- Repository: [cache-for-search](https://github.com/madebyaris/cache-for-search)

## License

This project is licensed under the GPL v2 or later - see the [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html) file for details.

## Support

If you encounter any issues or have questions, please [open an issue](https://github.com/madebyaris/cache-for-search/issues) on GitHub.
