# Upwork Tracker App

This repository contains a Symfony based application configured for local development using Docker Compose.

## Requirements

- Docker
- Docker Compose

## Getting Started

1. Clone the repository and install the dependencies using Composer (inside the container).
2. Copy `.env` to `.env.local` if you need to override any defaults.
3. Start the services:

```bash
docker-compose up -d
```

This command will start PHP, Nginx and a MySQL 8 database defined in `docker-compose.yml`.

The application will be available at [http://localhost:8080](http://localhost:8080).

The default database connection in `.env` points to the MySQL container:

```env
DATABASE_URL="mysql://app:!ChangeMe!@127.0.0.1:3306/app?serverVersion=8.0.32&charset=utf8mb4"
```

Ensure the credentials in `.env` match those provided for the MySQL service in `docker-compose.yml`.
