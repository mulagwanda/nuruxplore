# NuruXplore - AI Academic Writing Workspace

An intelligent workspace that helps students and researchers write academic papers, theses, and dissertations with AI assistance.

## Tech Stack
- **Backend:** Laravel 13
- **AI:** Groq API (LLaMA 3.3 70B)
- **Database:** MySQL
- **Frontend:** Vanilla JS + Alpine.js (Coming Day 3)

## Development Setup

### Prerequisites
- PHP 8.2+
- Composer
- MySQL 8.0+
- ServBay (Local Development)

### Installation

```bash
# Clone repository
git clone https://github.com/denema/nuruxplore.git
cd nuruxplore

# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate

# Start development server
php artisan serve

cd /var/www/nuruxplore
sudo git pull
sudo -u www-data php artisan optimize
sudo systemctl restart php8.3-fpm