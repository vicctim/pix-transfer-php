# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Pix Transfer is a PHP-based file sharing system inspired by WeTransfer, supporting uploads up to 10GB with modern responsive design, user authentication, admin panel, and automatic email notifications.

## Development Commands

### Testing
```bash
# Run all unit tests from project root
php run_tests.php

# Run tests from src directory
php src/run_tests.php

# Individual test classes available in tests/ directory
```

### Docker Development
```bash
# Start development environment
docker-compose up -d

# View logs
docker-compose logs -f

# Stop services
docker-compose down

# Build production image
docker build -f Dockerfile.prod -t vicctim/pix-transfer-php:latest . --no-cache

# Production deployment
docker-compose -f docker-compose.prod.yml up -d
```

### Database
```bash
# Access MySQL container
docker exec -it [container_name] mysql -u upload_user -pupload_password upload_system

# Database backup
docker exec [db_container] mysqldump -u root -proot_password upload_system > backup.sql
```

## Architecture

### Core Components
- **Models** (`src/models/`): Database entities using singleton Database pattern
  - `User.php`: Authentication, user management, role-based access
  - `UploadSession.php`: File sharing sessions with expiration tokens  
  - `File.php`: File metadata and storage management
  - `ShortUrl.php`: URL shortening functionality

- **Database** (`src/config/database.php`): Singleton PDO wrapper with prepared statements

- **Configuration**: Environment variables via `env.php` (copy from `env.php.example`)

### Request Flow
1. Authentication check via User model
2. Upload sessions created with unique tokens and expiration dates
3. Files stored with metadata tracking via File model
4. Email notifications sent with download links
5. Admin panel provides user and upload management

### Key Features
- Token-based file sharing with expiration
- Role-based access control (user/admin)
- File size tracking and formatting utilities
- Email integration via PHPMailer
- Custom test suite for unit testing

### Database Schema
- `users`: Authentication and role management
- `upload_sessions`: File sharing sessions with tokens/expiration
- `files`: File metadata and storage paths
- `short_urls`: URL shortening (recent addition)

### File Organization
- `/src`: Main application code
- `/uploads`: User uploaded files (organized by date)
- `/database`: SQL initialization scripts
- `/tests`: Custom unit test suite
- Multiple Docker compose files for different environments

## Environment Setup

### Required Environment Variables
- Database: `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`
- SMTP: `SMTP_HOST`, `SMTP_PORT`, `SMTP_FROM`
- Admin: `ADMIN_EMAIL`, `ADMIN_PASSWORD`

### Default Credentials
- Admin: `admin@transfer.com` / `password`

## Testing Strategy

The project uses a custom `TestSuite` class instead of PHPUnit:
- Base test class with assertion methods
- Separate test files for Login, Upload, and Download functionality
- Run via `php run_tests.php` from project root
- Tests verify database connectivity before execution

## Development Notes

- PHP application with MySQL backend
- Uses PDO with prepared statements for security
- Extensive logging to `/var/www/html/logs/app.log`
- File uploads organized by date structure (`uploads/YYYY/MM/DD/`)
- Built for containerized deployment with Traefik reverse proxy