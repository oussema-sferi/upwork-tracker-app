# Upwork Tracker App

## Overview
Internal solution to connect to Upwork API, check for jobs, notify via WhatsApp/Telegram, and generate proposals using ChatGPT.

## Features
- Upwork API integration
- Job monitoring with filters
- WhatsApp/Telegram notifications
- ChatGPT proposal generation
- Automated job checking (every 5-10 minutes)

## Environments
- **Production**: `upwork-tracker.oussema-sferi.dev`
- **Staging**: `staging-upwork-tracker.oussema-sferi.dev`
- **Local**: `http://localhost:8080`

## Database Management
- **phpMyAdmin**: `http://VPS_IP/phpmyadmin`
- **Production DB**: `upwork_tracker_prod` (port 3307)
- **Staging DB**: `upwork_tracker_staging` (port 3308)

## Environment Variables
- `VPS_IP`: Your VPS IP address
- `VPS_HOST`: Your VPS hostname
- `VPS_USER`: Your VPS username
- `VPS_SSH_KEY`: Your SSH private key

## Development Setup

### Local Development
```bash
# Start development environment
docker-compose --env-file .env.local up -d

# Stop development environment
docker-compose down

# View logs
docker-compose logs -f

# Access application
# Web: http://localhost:8080
# phpMyAdmin: http://localhost:8082
```

## Tech Stack
- **Framework**: Symfony 6.4 (LTS)
- **PHP**: 8.3
- **Database**: MySQL 8.0
- **Containerization**: Docker
- **CI/CD**: GitHub Actions
- **Deployment**: OVH Ubuntu VPS
- **Branch Protection**: Enabled for professional workflow
# Clean deployment
