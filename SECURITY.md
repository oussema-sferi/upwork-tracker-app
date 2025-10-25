# 🔐 Security & Environment Variables

## Overview
This project uses a secure environment variables approach to protect sensitive data in a public repository.

## 🛡️ Security Features

### ✅ What's Protected
- API keys and secrets are never committed to the repository
- Database passwords are encrypted
- Different secrets for each environment (dev/staging/prod)
- Local development is isolated from production

### ✅ What's Safe to Commit
- `.env` (with null/default values)
- `.env.example` (template for developers)
- Docker Compose files (using environment variables)
- GitHub Actions (using GitHub Secrets)

## 📁 File Structure

```
.env                    # Default values (safe to commit)
.env.local             # Local secrets (gitignored)
.env.prod.local        # Production secrets (gitignored)
.env.staging.local     # Staging secrets (gitignored)
.env.example           # Template for developers
```

## 🔧 Setup Instructions

### 1. Local Development
```bash
# Copy the template
cp .env.example .env.local

# Edit with your actual values
nano .env.local
```

### 2. GitHub Secrets
Add these secrets in your GitHub repository settings:
- `MYSQL_PASSWORD_PROD`
- `MYSQL_PASSWORD_STAGING`
- `UPWORK_API_KEY`
- `UPWORK_API_SECRET`

### 3. Symfony Secrets (Optional)
```bash
# Run the setup script
./setup-secrets.sh
```

## 🚀 Deployment

### Development
- Uses `.env.local` for secrets
- Docker Compose loads environment variables

### Staging
- GitHub Actions sets environment variables
- Deploys with staging secrets

### Production
- GitHub Actions sets production environment variables
- Uses encrypted Symfony secrets

## 🔑 Upwork API Integration

The project is configured with Upwork API credentials:
- **API Key**: Set in environment variables
- **API Secret**: Set in environment variables  
- **Callback URL**: Environment-specific URLs
- **OAuth 2.0**: Configured for web application

## 📋 Environment Variables

### Required Variables
- `UPWORK_API_KEY`: Your Upwork API key
- `UPWORK_API_SECRET`: Your Upwork API secret
- `UPWORK_CALLBACK_URL`: OAuth callback URL
- `MYSQL_PASSWORD`: Database password
- `DATABASE_URL`: Complete database connection string

### Optional Variables
- `APP_ENV`: Application environment (dev/staging/prod)
- `APP_DEBUG`: Debug mode (0/1)

## 🛠️ Troubleshooting

### Missing Environment Variables
```bash
# Check if variables are loaded
docker-compose exec php env | grep UPWORK
```

### Local Development Issues
```bash
# Ensure .env.local exists
ls -la .env.local

# Check Docker Compose logs
docker-compose logs php
```

## 🔒 Security Best Practices

1. **Never commit sensitive data** to the repository
2. **Use different secrets** for each environment
3. **Rotate API keys** regularly
4. **Monitor access logs** for unauthorized usage
5. **Use strong passwords** for database access

## 📞 Support

If you encounter issues with environment variables or secrets:
1. Check the `.env.example` file for required variables
2. Verify GitHub Secrets are properly configured
3. Ensure Docker Compose is loading environment variables
4. Check Symfony secrets are encrypted properly
