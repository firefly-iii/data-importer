# Data Importer Development Environment

## Quick Start

The data-importer uses Laravel's built-in development server. No Apache, no PHP-FPM, no SELinux complexity.

### Prerequisites
- PHP 8.4+ (already installed on trashcan)
- Composer (already installed)
- Git access to lil-debian deployment repository

### Start Development Server

#### Option 1: Systemd Service (Recommended)
```bash
./.linuxdev/start
```

#### Option 2: Manual
```bash
php artisan serve --host=127.0.0.1 --port=3000
```

Access at: `http://localhost:3000`

#### Service Management
```bash
# Stop the service
./.linuxdev/stop

# Follow Logs
journalctl --user -u laravel-dev-data-importer -f

# View last 50 Logs
journalctl --user -u laravel-dev-data-importer -n 50

# Check service status
systemctl --user status laravel-dev-data-importer
```

### Initial Setup (if needed)
```bash
# Install dependencies
composer install

# Generate application key (if missing)
php artisan key:generate
```

### Cache Control
```bash
# Clear all caches
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
```

## Development Workflow

### Local Changes
1. Edit files in `~/Code/skellnet/src/data-importer/`
2. Changes are immediately reflected (no restart needed)
3. Check Laravel logs: `storage/logs/laravel.log`

### Deploy to Production
```bash
# Commit changes locally
git add .
git commit -m "Description of changes"

# Deploy to lil-debian
ssh root@lil-debian "cd /opt/data-importer && git pull"
ssh root@lil-debian "systemctl restart php8.4-fpm"
```

## Environment Configuration

### Local (.env)
- `APP_ENV=local`
- `APP_DEBUG=true` (for detailed error messages)
- Database connections point to development instances

### Production (lil-debian)
- `APP_ENV=production`
- `APP_DEBUG=false`
- Real database connections
- Nginx + PHP-FPM stack

## Debugging

### Application Errors
- Check `storage/logs/laravel.log` for detailed error traces
- Set `APP_DEBUG=true` in `.env` for browser error display
- Use `app('log')->debug('message', $data)` for custom logging

### Common Issues
- **500 errors**: Check Laravel log first, not web server logs
- **Permission issues**: Ensure `storage/` and `bootstrap/cache/` are writable
- **Missing dependencies**: Run `composer install`
- **Cache problems**: Clear all Laravel caches with artisan commands

## Frontend Assets

If frontend compilation is required:
```bash
npm install
npm run dev    # for development
npm run build  # for production
```

## Database

For local database development, configure connections in `.env`:
- Use local MySQL/PostgreSQL instances
- Or connect directly to development databases on the network
- Run migrations: `php artisan migrate`

## Testing

```bash
# Run PHP tests
php artisan test

# Run specific test file
php artisan test --filter SpecificTestClass
```

## Production Comparison

| Aspect | Development (trashcan) | Production (lil-debian) |
|--------|------------------------|-------------------------|
| Web Server | Laravel dev server | Nginx + PHP-FPM |
| Port | 3000 | 443 (HTTPS) |
| Debug | Enabled | Disabled |
| Logs | `storage/logs/` | `storage/logs/` + syslog |
| SSL | None | Full chain via acme.sh |

The development server handles PHP execution directly, eliminating the web server complexity needed in production.
