# Docker Deployment Guide

## Quick Start with Docker

### Development Environment

1. **Clone and Start:**
```bash
git clone https://github.com/franpass87/FP-Digital-Marketing-Suite.git
cd FP-Digital-Marketing-Suite
docker-compose up -d
```

2. **Access Services:**
- WordPress: http://localhost:8080
- phpMyAdmin: http://localhost:8081
- Redis: localhost:6379

3. **WordPress Setup:**
- Follow WordPress installation wizard
- Activate FP Digital Marketing Suite plugin

### Production Deployment

1. **Environment Variables:**
Create `.env` file:
```bash
# Database Configuration
DB_HOST=your-db-host
DB_NAME=your-db-name
DB_USER=your-db-user
DB_PASSWORD=your-secure-password

# WordPress Configuration
WP_DEBUG=false
WP_DEBUG_LOG=false

# Redis Configuration
REDIS_HOST=your-redis-host
REDIS_PORT=6379
```

2. **Production Docker Compose:**
```yaml
version: '3.8'
services:
  wordpress:
    build: .
    environment:
      WORDPRESS_DB_HOST: ${DB_HOST}
      WORDPRESS_DB_NAME: ${DB_NAME}
      WORDPRESS_DB_USER: ${DB_USER}
      WORDPRESS_DB_PASSWORD: ${DB_PASSWORD}
      WORDPRESS_DEBUG: ${WP_DEBUG}
    volumes:
      - wordpress_data:/var/www/html
    networks:
      - wp_network

volumes:
  wordpress_data:
networks:
  wp_network:
```

3. **SSL and Reverse Proxy:**
Use nginx or Traefik for SSL termination and reverse proxy.

### Container Management

**Start Services:**
```bash
docker-compose up -d
```

**Stop Services:**
```bash
docker-compose down
```

**View Logs:**
```bash
docker-compose logs -f wordpress
```

**Database Backup:**
```bash
docker exec fp-digital-marketing-db mysqldump -u root -p fp_digital_marketing > backup.sql
```

**Update Plugin:**
```bash
docker-compose down
git pull origin main
docker-compose up -d --build
```

### Performance Optimization

1. **Enable Redis Object Cache:**
Add to wp-config.php:
```php
define('WP_REDIS_HOST', 'redis');
define('WP_REDIS_PORT', 6379);
```

2. **Resource Limits:**
```yaml
services:
  wordpress:
    deploy:
      resources:
        limits:
          memory: 512M
        reservations:
          memory: 256M
```

### Monitoring

**Health Check:**
```bash
docker exec fp-digital-marketing-wp curl -f http://localhost/wp-admin/admin-ajax.php?action=fp_health_check
```

**Container Stats:**
```bash
docker stats fp-digital-marketing-wp
```