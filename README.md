# HLstatsX Stats API

A modern Laravel application for tracking and analyzing game server statistics, providing real-time player rankings, weapon statistics, and comprehensive monitoring.

## Features

### Core Functionality
- **Player Statistics** - Track player performance across multiple game servers
- **Real-time Rankings** - Dynamic skill-based player leaderboards
- **Weapon Analytics** - Detailed weapon usage and effectiveness statistics
- **Server Monitoring** - Live server status and player counts
- **Kill Feed** - Real-time event tracking and visualization
- **Map Statistics** - Map-specific performance metrics

### Technical Features
- **Event Processing** - Asynchronous log processing with queue support
- **Skill Calculation** - ELO-based skill rating system
- **Performance Optimized** - Sub-500ms API response times
- **Health Monitoring** - Comprehensive system health checks
- **API Documentation** - RESTful API with consistent JSON responses
- **Real-time Updates** - WebSocket support for live updates

## Tech Stack

- **Backend:** Laravel 12.x, PHP 8.3
- **Frontend:** Vue 3, Inertia.js v2, Tailwind CSS v4
- **Database:** MySQL 8.0
- **Cache:** Redis (configurable)
- **Queue:** Laravel Queue (database/redis)
- **Testing:** Pest 4, Vitest

## Requirements

- PHP 8.3 or higher
- Composer 2.x
- Node.js 20.x or higher
- MySQL 8.0 or higher
- Redis (optional, for caching/queues)

## Installation

### 1. Clone the Repository

```bash
git clone https://github.com/jekinney/stats.git
cd stats
```

### 2. Install Dependencies

```bash
composer install
npm install
```

### 3. Environment Configuration

```bash
cp .env.example .env
php artisan key:generate
```

Configure your `.env` file:

```env
APP_NAME="HLstatsX Stats"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=stats
DB_USERNAME=root
DB_PASSWORD=

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
```

### 4. Database Setup

```bash
php artisan migrate
php artisan db:seed # Optional: seed with test data
```

### 5. Build Assets

```bash
npm run build
# or for development with hot reload:
npm run dev
```

### 6. Start the Application

```bash
php artisan serve
```

Visit `http://localhost:8000` to access the application.

## API Documentation

### Authentication
Currently, the API is open. Authentication can be added via Laravel Sanctum.

### Core Endpoints

#### Player Rankings
```
GET /api/players/rankings?game={game_code}&per_page=20
```

#### Player Profile
```
GET /api/players/{player_id}
```

#### Weapon Statistics
```
GET /api/weapons/statistics?game={game_code}
```

#### Server List
```
GET /api/servers
```

#### Recent Frags
```
GET /api/frags/recent?limit=50
```

#### Health Check
```
GET /api/health
```

#### System Metrics
```
GET /api/metrics
```

### Response Format

All API responses follow this structure:

```json
{
  "data": [...],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 100
  }
}
```

## Event Processing

The application processes game server log events asynchronously:

### Processing Flow
1. Log events received via UDP listener
2. Events parsed and validated
3. Jobs dispatched to queue
4. Player statistics updated
5. Skill ratings recalculated
6. Cache invalidated selectively

### Running the Queue Worker

```bash
php artisan queue:work --queue=default
```

For production, use Supervisor to manage queue workers.

## Testing

### PHP Tests (Pest)

```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --filter=Integration

# Run with coverage
php artisan test --coverage
```

### Vue Tests (Vitest)

```bash
# Run all tests
npm run test

# Run in watch mode
npm run test:watch

# Run with coverage
npm run test:coverage
```

### Test Coverage

- **206 PHP tests** covering backend logic, API endpoints, and integrations
- **52 Vue tests** covering frontend components
- **662 total assertions**
- Organized by feature: Unit, Integration, Performance, Monitoring

## Performance Benchmarks

The application meets these performance targets:

- Player rankings query: <500ms (5000 players)
- Player profile retrieval: <500ms (500 frags)
- API endpoints: <300ms average
- Burst event handling: 100 events in <10s
- Database queries: <50ms (indexed)

## Monitoring & Health Checks

### Health Endpoints

- `/api/health` - Overall system health
- `/api/metrics` - System metrics
- `/api/monitoring/status` - Detailed status
- `/api/monitoring/performance` - Response times

### Monitoring Features

- Database connectivity checks
- Cache health verification
- Queue status monitoring
- Error rate tracking
- Performance metrics

## Deployment

### Production Checklist

1. **Environment Configuration**
   ```bash
   APP_ENV=production
   APP_DEBUG=false
   ```

2. **Optimize Application**
   ```bash
   composer install --optimize-autoloader --no-dev
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   npm run build
   ```

3. **Database Migrations**
   ```bash
   php artisan migrate --force
   ```

4. **Queue Workers**
   Configure Supervisor to run queue workers:
   ```ini
   [program:stats-worker]
   command=php /path/to/stats/artisan queue:work
   autostart=true
   autorestart=true
   user=www-data
   ```

5. **Scheduled Tasks**
   Add to crontab:
   ```
   * * * * * cd /path/to/stats && php artisan schedule:run >> /dev/null 2>&1
   ```

6. **Web Server**
   - Configure Nginx/Apache to point to `public/` directory
   - Enable HTTPS with Let's Encrypt
   - Configure CORS if needed

### Docker Deployment

See `docker-compose.yml` for containerized deployment.

## Development

### Code Style

The project uses Laravel Pint for PHP formatting:

```bash
vendor/bin/pint
```

ESLint and Prettier for JavaScript/Vue:

```bash
npm run lint
npm run format
```

### Git Workflow

1. Create feature branch: `git checkout -b feature/my-feature`
2. Make changes and commit: `git commit -m "Add feature"`
3. Run tests: `php artisan test && npm run test`
4. Push and create PR: `git push origin feature/my-feature`

## Architecture

### Backend Structure

```
app/
├── Actions/          # Business logic actions
├── Http/
│   ├── Controllers/  # API controllers
│   ├── Requests/     # Form requests
│   └── Resources/    # API resources
├── Jobs/             # Queue jobs
├── Models/           # Eloquent models
└── Services/         # Service classes

tests/
├── Feature/
│   ├── Api/          # API endpoint tests
│   ├── Integration/  # Integration tests
│   ├── Performance/  # Performance tests
│   ├── Monitoring/   # Monitoring tests
│   └── Deployment/   # Deployment readiness
└── Unit/             # Unit tests
```

### Frontend Structure

```
resources/
├── js/
│   ├── components/   # Vue components
│   ├── pages/        # Inertia pages
│   └── app.ts        # Application entry
└── css/
    └── app.css       # Tailwind styles
```

## Contributing

1. Fork the repository
2. Create your feature branch
3. Write tests for new features
4. Ensure all tests pass
5. Submit a pull request

## License

This project is licensed under the MIT License.

## Support

For issues, questions, or contributions:
- GitHub Issues: https://github.com/jekinney/stats/issues
- Documentation: https://github.com/jekinney/stats/wiki

## Credits

Built with Laravel, Vue.js, and other amazing open-source projects.

---

**Version:** 1.0.0  
**Last Updated:** February 9, 2026
