#!/bin/bash

# Symfony Secrets Setup Script
# This script helps you set up encrypted secrets for different environments

echo "🔐 Setting up Symfony Secrets for Upwork API..."

# Production secrets
echo "Setting production secrets..."
php bin/console secrets:set UPWORK_API_KEY --env=prod
php bin/console secrets:set UPWORK_API_SECRET --env=prod

# Staging secrets  
echo "Setting staging secrets..."
APP_ENV=staging php bin/console secrets:set UPWORK_API_KEY
APP_ENV=staging php bin/console secrets:set UPWORK_API_SECRET

echo "✅ Secrets setup complete!"
echo ""
echo "📋 Next steps:"
echo "1. Add these secrets to your GitHub repository:"
echo "   - MYSQL_PASSWORD_PROD"
echo "   - MYSQL_PASSWORD_STAGING" 
echo "   - UPWORK_API_KEY"
echo "   - UPWORK_API_SECRET"
echo ""
echo "2. Go to: https://github.com/your-username/your-repo/settings/secrets/actions"
