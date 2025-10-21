# Rekaz Laravel Application - Docker Setup

This document provides instructions for running the Rekaz Laravel application using Docker.

## Prerequisites

- Docker Desktop installed and running
- Git (to clone the repository)
- At least 4GB of available RAM

## Quick Start

1. **Clone the repository and switch to docker branch:**
   ```bash
   git clone <repository-url>
   cd Rekaz
   git checkout docker-version
   ```

2. **Start Docker Desktop** (make sure it's running)

3. **Build and start the containers:**
   ```bash
   # Copy environment file
   cp .env.docker .env
   
   # Build containers
   docker compose build
   
   # Start services
   docker compose up -d
   ```

4. **Access the application:**
   - **Main Application:** http://localhost:8080
   - **API Documentation (Swagger):** http://localhost:8080/api/documentation
   - **phpMyAdmin:** http://localhost:8081

## Services

The Docker setup includes the following services:

### Application (rekaz-app)
- **Port:** 8080
- **Technology:** PHP 8.2, Laravel, Nginx, Supervisor
- **Features:** 
  - Laravel application with all dependencies
  - Nginx web server
  - PHP-FPM for processing
  - Queue worker for background jobs
  - Swagger API documentation

### Database (rekaz-mysql)
- **Port:** 3306
- **Technology:** MySQL 8.0
- **Credentials:**
  - Database: `rekaz`
  - User: `rekaz_user`
  - Password: `rekaz_password`
  - Root Password: `root_password`

### Cache (rekaz-redis)
- **Port:** 6379
- **Technology:** Redis 7 Alpine
- **Usage:** Session storage, caching, queue backend

### Database Management (rekaz-phpmyadmin)
- **Port:** 8081
- **Technology:** phpMyAdmin
- **Access:** Use database credentials above

## Docker Commands

### Basic Operations
```bash
# Start all services
docker compose up -d

# Stop all services
docker compose down

# View logs
docker compose logs -f

# View logs for specific service
docker compose logs -f app

# Rebuild containers
docker compose build --no-cache

# Restart a specific service
docker compose restart app
```

### Development Commands
```bash
# Access application container shell
docker compose exec app bash

# Run Laravel commands
docker compose exec app php artisan migrate
docker compose exec app php artisan cache:clear
docker compose exec app php artisan queue:work

# Run Composer commands
docker compose exec app composer install
docker compose exec app composer update
```

## File Structure

```
docker/
├── nginx/
│   ├── nginx.conf          # Main Nginx configuration
│   └── default.conf        # Laravel-specific virtual host
├── php/
│   └── php.ini            # PHP configuration
├── mysql/
│   └── init.sql           # Database initialization
├── supervisord.conf       # Process manager configuration
└── entrypoint.sh         # Container startup script
```

## Environment Configuration

The application uses `.env.docker` for Docker-specific settings:

- Database connection points to `mysql` service
- Redis connection points to `redis` service
- Session driver set to Redis
- Cache driver set to Redis
- Queue connection set to Redis

## Troubleshooting

### Container Won't Start
1. Check if Docker Desktop is running
2. Ensure ports 8080, 3306, 6379, 8081 are not in use
3. Check logs: `docker compose logs`

### Database Connection Issues
1. Wait for MySQL to fully initialize (can take 30-60 seconds)
2. Check MySQL logs: `docker compose logs mysql`
3. Verify database credentials in `.env`

### Permission Issues
1. The entrypoint script sets proper permissions automatically
2. If issues persist, run: `docker compose exec app chown -R www-data:www-data /var/www/html/storage`

### Application Not Loading
1. Check if all containers are running: `docker compose ps`
2. Check application logs: `docker compose logs app`
3. Verify Nginx configuration: `docker compose exec app nginx -t`

## Development Workflow

1. **Make code changes** in your local files (they're mounted as volumes)
2. **Clear caches** if needed: `docker compose exec app php artisan cache:clear`
3. **Run migrations** for database changes: `docker compose exec app php artisan migrate`
4. **Restart services** if configuration changes: `docker compose restart`

## Production Considerations

For production deployment:

1. Use proper environment variables
2. Set `APP_DEBUG=false`
3. Configure proper SSL certificates
4. Use external database and Redis services
5. Implement proper backup strategies
6. Configure log rotation
7. Set up monitoring and health checks

## API Documentation

The application includes Swagger API documentation available at:
http://localhost:8080/api/documentation

The documentation is automatically generated from controller annotations and includes:
- Authentication endpoints
- User profile endpoints
- Request/response schemas
- Interactive API testing

## Support

For issues related to:
- **Docker setup:** Check this README and Docker logs
- **Laravel application:** Check Laravel logs in `storage/logs/`
- **Database issues:** Check MySQL logs and phpMyAdmin
- **Performance:** Monitor container resources with `docker stats`