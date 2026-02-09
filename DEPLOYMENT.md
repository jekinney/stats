# Deployment Guide

This guide covers deploying the HLstatsX Stats API to production.

## Pre-Deployment Checklist

Before deploying to production, verify all the following:

### ✅ Code Quality
- [ ] All tests passing (225 PHP + 52 Vue = 277 tests)
- [ ] Run `vendor/bin/pint` to ensure code style consistency
- [ ] Run `npm run lint` to check JavaScript/Vue code
- [ ] Review and merge all pull requests
- [ ] Tag release version in git

### ✅ Environment Configuration
- [ ] `.env.production` or production `.env` configured
- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_KEY` generated (32 character base64 key)
- [ ] `APP_URL` set to production domain (https://stats.yourdomain.com)

### ✅ Database
- [ ] Production database created
- [ ] Database credentials configured in `.env`
- [ ] Connection tested from application server
- [ ] Backup strategy established
- [ ] Migration plan documented

### ✅ Security
- [ ] All secrets rotated (database passwords, API keys)
- [ ] SSL/TLS certificates installed
- [ ] Force HTTPS enabled
- [ ] CORS configured (`config/cors.php`)
- [ ] Rate limiting configured
- [ ] Security headers configured (CSP, X-Frame-Options, etc.)

### ✅ Performance
- [ ] Redis installed and configured
- [ ] `CACHE_STORE=redis` in `.env`
- [ ] `QUEUE_CONNECTION=redis` in `.env`
- [ ] `SESSION_DRIVER=redis` in `.env`
- [ ] Opcache enabled in PHP configuration
- [ ] Database indexes verified

### ✅ Monitoring
- [ ] Health check endpoint accessible (`/api/health`)
- [ ] Monitoring endpoints secured or restricted
- [ ] Log aggregation configured (e.g., Sentry, Papertrail)
- [ ] Uptime monitoring configured (e.g., UptimeRobot)
- [ ] Performance monitoring configured (e.g., New Relic, Scout)

## Server Requirements

### Minimum Requirements
- **PHP:** 8.3 or higher
- **Web Server:** Nginx 1.18+ or Apache 2.4+
- **Database:** MySQL 8.0+ or MariaDB 10.5+
- **Cache:** Redis 6.0+
- **Memory:** 2GB RAM minimum, 4GB recommended
- **Storage:** 10GB minimum, SSD recommended

### PHP Extensions
```bash
php -m | grep -E '(BCMath|Ctype|JSON|Mbstring|OpenSSL|PDO|Tokenizer|XML)'
```

Required extensions:
- BCMath
- Ctype
- JSON
- Mbstring
- OpenSSL
- PDO
- PDO_MySQL
- Tokenizer
- XML

## Deployment Steps

### 1. Server Setup

#### Install Dependencies
```bash
# Ubuntu/Debian
sudo apt update
sudo apt install -y php8.3 php8.3-fpm php8.3-mysql php8.3-redis \
    php8.3-mbstring php8.3-xml php8.3-bcmath php8.3-curl \
    mysql-server redis-server nginx composer
```

#### Configure PHP-FPM
Edit `/etc/php/8.3/fpm/pool.d/www.conf`:
```ini
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 500
```

Restart PHP-FPM:
```bash
sudo systemctl restart php8.3-fpm
```

### 2. Clone Repository

```bash
cd /var/www
sudo git clone https://github.com/jekinney/stats.git
cd stats
sudo chown -R www-data:www-data .
```

### 3. Install Dependencies

```bash
# PHP dependencies
composer install --optimize-autoloader --no-dev

# JavaScript dependencies
npm ci
npm run build
```

### 4. Environment Configuration

```bash
# Copy and configure environment file
cp .env.example .env
php artisan key:generate
```

Edit `.env`:
```env
APP_NAME="HLstatsX Stats"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://stats.yourdomain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hlstatsx_stats
DB_USERNAME=stats_user
DB_PASSWORD=secure_password_here

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### 5. Database Migration

```bash
# Run migrations
php artisan migrate --force

# Optional: Seed with initial data
php artisan db:seed --force
```

### 6. Optimize Application

```bash
# Cache configuration
php artisan config:cache

# Cache routes
php artisan route:cache

# Cache views
php artisan view:cache

# Generate optimized autoloader
composer dump-autoload --optimize
```

### 7. Set Permissions

```bash
sudo chown -R www-data:www-data /var/www/stats
sudo chmod -R 755 /var/www/stats
sudo chmod -R 775 /var/www/stats/storage
sudo chmod -R 775 /var/www/stats/bootstrap/cache
```

### 8. Configure Nginx

Create `/etc/nginx/sites-available/stats`:
```nginx
server {
    listen 80;
    listen [::]:80;
    server_name stats.yourdomain.com;
    
    # Redirect to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name stats.yourdomain.com;

    root /var/www/stats/public;
    index index.php;

    # SSL Configuration
    ssl_certificate /etc/letsencrypt/live/stats.yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/stats.yourdomain.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;

    # Logging
    access_log /var/log/nginx/stats-access.log;
    error_log /var/log/nginx/stats-error.log;

    # Client max body size
    client_max_body_size 10M;

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_types text/plain text/css text/xml text/javascript 
               application/x-javascript application/xml+rss 
               application/json application/javascript;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Enable site:
```bash
sudo ln -s /etc/nginx/sites-available/stats /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### 9. SSL Certificate (Let's Encrypt)

```bash
# Install Certbot
sudo apt install -y certbot python3-certbot-nginx

# Obtain certificate
sudo certbot --nginx -d stats.yourdomain.com

# Auto-renewal is configured automatically
```

### 10. Queue Worker Setup

Create systemd service `/etc/systemd/system/stats-worker.service`:
```ini
[Unit]
Description=Stats Queue Worker
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/stats
ExecStart=/usr/bin/php /var/www/stats/artisan queue:work --sleep=3 --tries=3 --max-time=3600
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

Enable and start:
```bash
sudo systemctl daemon-reload
sudo systemctl enable stats-worker
sudo systemctl start stats-worker
sudo systemctl status stats-worker
```

### 11. Scheduled Tasks (Cron)

Add to crontab for `www-data` user:
```bash
sudo crontab -u www-data -e
```

Add:
```cron
* * * * * cd /var/www/stats && php artisan schedule:run >> /dev/null 2>&1
```

### 12. Verify Deployment

```bash
# Check health endpoint
curl https://stats.yourdomain.com/api/health

# Check metrics
curl https://stats.yourdomain.com/api/metrics

# Run production readiness tests
php artisan test tests/Feature/Deployment
```

## Post-Deployment

### Monitoring Setup

1. **Uptime Monitoring**
   - Configure UptimeRobot or similar: `/api/health`
   - Alert on: Down, slow response (>2s)

2. **Log Monitoring**
   - Configure log aggregation (Sentry, Papertrail)
   - Monitor `storage/logs/laravel.log`
   - Set up alerts for errors

3. **Performance Monitoring**
   - Configure APM (New Relic, Scout)
   - Monitor `/api/monitoring/performance`
   - Track slow queries (>100ms)

### Backup Strategy

1. **Database Backups**
```bash
# Daily automated backup
0 2 * * * mysqldump -u stats_user -p'password' hlstatsx_stats | gzip > /backups/stats-$(date +\%Y\%m\%d).sql.gz
```

2. **Application Backups**
```bash
# Weekly application backup
0 3 * * 0 tar -czf /backups/stats-app-$(date +\%Y\%m\%d).tar.gz /var/www/stats
```

3. **Retention Policy**
   - Keep daily backups for 7 days
   - Keep weekly backups for 4 weeks
   - Keep monthly backups for 12 months

### Maintenance Tasks

**Weekly:**
- Review error logs
- Check queue worker status
- Monitor disk space
- Review slow query log

**Monthly:**
- Update dependencies: `composer update`
- Review and optimize database queries
- Check for security updates
- Test backup restoration

**Quarterly:**
- Load testing with realistic traffic
- Security audit
- Review and update documentation
- Capacity planning review

## Rollback Procedure

If deployment issues occur:

1. **Immediate Rollback**
```bash
cd /var/www/stats
git checkout previous-release-tag
composer install --no-dev
php artisan migrate:rollback --step=1
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo systemctl restart php8.3-fpm
```

2. **Database Rollback**
```bash
# Restore database from backup
mysql -u stats_user -p hlstatsx_stats < /backups/stats-backup.sql
```

3. **Verify Rollback**
```bash
curl https://stats.yourdomain.com/api/health
php artisan test
```

## Troubleshooting

### Common Issues

**500 Internal Server Error**
- Check PHP error logs: `/var/log/php8.3-fpm.log`
- Check Laravel logs: `storage/logs/laravel.log`
- Verify permissions: `storage/` and `bootstrap/cache/`
- Clear cache: `php artisan cache:clear`

**Queue Jobs Not Processing**
- Check worker status: `systemctl status stats-worker`
- View worker logs: `journalctl -u stats-worker -f`
- Check Redis connection
- Restart worker: `systemctl restart stats-worker`

**Slow Response Times**
- Enable query logging to identify slow queries
- Check Redis cache hit rate
- Review Opcache status: `php -i | grep opcache`
- Check database indexes

**Database Connection Issues**
- Verify credentials in `.env`
- Test connection: `php artisan tinker` → `DB::connection()->getPdo()`
- Check MySQL status: `systemctl status mysql`
- Review MySQL error log: `/var/log/mysql/error.log`

## Security Best Practices

1. **Keep Software Updated**
   - Regularly update PHP, MySQL, Nginx
   - Update Laravel and dependencies monthly
   - Subscribe to security advisories

2. **Access Control**
   - Use SSH keys, disable password authentication
   - Implement firewall rules (UFW/iptables)
   - Restrict database access to localhost
   - Secure monitoring endpoints

3. **Application Security**
   - Never commit `.env` file
   - Rotate secrets regularly
   - Use prepared statements (Laravel does this)
   - Implement rate limiting on all endpoints

4. **Monitoring**
   - Enable failed login notifications
   - Monitor for unusual traffic patterns
   - Set up intrusion detection (Fail2ban)
   - Review access logs regularly

## Performance Optimization

1. **Database**
   - Add indexes on frequently queried columns
   - Use Redis for caching
   - Optimize queries with `SELECT` specific columns
   - Use eager loading to prevent N+1 queries

2. **Caching**
   - Enable Opcache in `php.ini`
   - Configure Redis for cache/sessions/queues
   - Use Laravel's cache facade liberally
   - Cache configuration/routes/views

3. **Web Server**
   - Enable gzip compression
   - Configure browser caching
   - Use HTTP/2
   - Enable CDN for static assets

## Support

For deployment issues or questions:
- GitHub Issues: https://github.com/jekinney/stats/issues
- Documentation: https://github.com/jekinney/stats/wiki

---

**Deployment Checklist Last Updated:** February 9, 2026
