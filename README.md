# Redis for Search - WordPress Plugin

[![License: GPL v2 or later](https://img.shields.io/badge/License-GPL%20v2%20or%20later-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

A high-performance WordPress plugin that enhances search functionality by implementing Redis caching. This plugin significantly improves search performance and reduces database load by caching search results.

## Features

- 🚀 Redis-powered search result caching
- 📊 Cache statistics tracking (hits/misses)
- 🔄 Automatic cache invalidation on post updates
- ⚙️ Smart caching strategies
- 🛠️ Configurable Redis connection settings
- 🎯 Admin interface for easy configuration

## Requirements

- PHP 7.1 or higher
- WordPress 5.0 or higher
- Redis server (optional)
- PHP Redis extension

## Installation

1. Download the plugin from [GitHub](https://github.com/madebyaris/cache-for-search)
2. Upload the plugin files to the `/wp-content/plugins/redis-for-search` directory
3. Activate the plugin through the 'Plugins' screen in WordPress
4. Configure the plugin settings under 'Settings > Redis for Search'

## Configuration

1. Navigate to 'Settings > Redis for Search' in your WordPress admin panel
2. Configure your Redis connection settings:
   - Host (default: localhost)
   - Port (default: 6379)
   - Cache type
3. Optional: Enable auto-revalidation for automatic cache updates

## Usage

Once configured, the plugin works automatically. It will:
- Cache search results in Redis
- Serve cached results for identical searches
- Automatically invalidate cache when posts are updated
- Track cache performance statistics

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
