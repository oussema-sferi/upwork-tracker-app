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
- **phpMyAdmin**: `http://51.222.24.219/phpmyadmin`
- **Production DB**: `upwork_tracker_prod` (port 3307)
- **Staging DB**: `upwork_tracker_staging` (port 3308)

## Tech Stack
- **Framework**: Symfony 6.4 (LTS)
- **PHP**: 8.3
- **Database**: MySQL 8.0
- **Containerization**: Docker
- **CI/CD**: GitHub Actions
- **Deployment**: OVH Ubuntu VPS
