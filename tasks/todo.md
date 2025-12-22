## Table of Contents

1. [Overview](#1-overview)
2. [Tech Stack & Dependencies](#2-tech-stack--dependencies)
3. [Project Setup & Docker](#3-project-setup--docker)
4. [Database Schema](#4-database-schema)
5. [Authentication System](#5-authentication-system)
6. [API Endpoints](#6-api-endpoints)
7. [Business Logic & Services](#7-business-logic--services)
8. [Stripe Integration](#8-stripe-integration)
9. [Email Notifications](#9-email-notifications)
10. [File Uploads (S3)](#10-file-uploads-s3)
11. [Reports & Excel Export](#11-reports--excel-export)
12. [Authorization & Policies](#12-authorization--policies)
13. [File Structure](#13-file-structure)
14. [Environment Variables](#14-environment-variables)
15. [Testing Requirements](#15-testing-requirements)
16. [Deployment Checklist](#16-deployment-checklist)

---

## 1. Overview

### Project Description
REST API backend for an event ticketing platform. Supports event creation, ticket sales via Stripe Checkout, role-based access control with category-based permissions, and Excel-exportable reports.

### Core Features
- Admin authentication (email/password + Google OAuth)
- Category-based event organization
- Event CRUD with registration deadlines
- Additional purchasable items per event
- Stripe Checkout integration
- Role-based permissions by category
- Sales reports with Excel export
- S3 image uploads for event hero images

### User Roles
| Role | Description |
|------|-------------|
| `super_admin` | Full access to all events, users, categories, and settings |
| `admin` | Access only to events within assigned categories |
| `viewer` | Read-only access to events within assigned categories |

---

## 2. Tech Stack & Dependencies

### Core Framework
- PHP 8.3
- Laravel 12.x
- MySQL 8.0

### Required Packages (composer.json)
```json
{
  "require": {
    "php": "^8.2",
    "laravel/framework": "^12.0",
    "laravel/sanctum": "^4.1",
    "laravel/fortify": "^1.27",
    "laravel/cashier": "^15.7",
    "laravel/socialite": "^5.21",
    "socialiteproviders/google": "^4.1",
    "league/flysystem-aws-s3-v3": "^3.0",
    "symfony/mailgun-mailer": "^7.3",
    "symfony/http-client": "^7.3",
    "maatwebsite/excel": "^3.1"
  },
  "require-dev": {
    "fakerphp/faker": "^1.23",
    "laravel/pail": "^1.2.2",
    "laravel/pint": "^1.13",
    "laravel/sail": "^1.41",
    "mockery/mockery": "^1.6",
    "nunomaduro/collision": "^8.6",
    "phpunit/phpunit": "^11.5.3"
  }
}
```

### Additional Package Installation
```bash
composer require maatwebsite/excel
php artisan vendor:publish --provider="Maatwebsite\Excel\ExcelServiceProvider" --tag=config
```

---

## 3. Project Setup & Docker

### 3.1 Dockerfile
```dockerfile
FROM ubuntu:22.04

LABEL maintainer="EventHub"

ARG WWWGROUP=1000
ARG NODE_VERSION=20
ARG MYSQL_CLIENT="mysql-client"

WORKDIR /var/www/html

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=UTC
ENV SUPERVISOR_PHP_COMMAND="/usr/bin/php -d variables_order=EGPCS /var/www/html/artisan serve --host=0.0.0.0 --port=80"
ENV SUPERVISOR_PHP_USER="sail"

RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

RUN apt-get update \
    && mkdir -p /etc/apt/keyrings \
    && apt-get install -y gnupg gosu curl ca-certificates zip unzip git supervisor sqlite3 libcap2-bin libpng-dev dnsutils librsvg2-bin nano \
    && curl -sS 'https://keyserver.ubuntu.com/pks/lookup?op=get&search=0x14aa40ec0831756756d7f66c4f4ea0aae5267a6c' | gpg --dearmor | tee /etc/apt/keyrings/ppa_ondrej_php.gpg > /dev/null \
    && echo "deb [signed-by=/etc/apt/keyrings/ppa_ondrej_php.gpg] https://ppa.launchpadcontent.net/ondrej/php/ubuntu jammy main" > /etc/apt/sources.list.d/ppa_ondrej_php.list \
    && apt-get update \
    && apt-get install -y php8.3-cli php8.3-dev \
       php8.3-pgsql php8.3-sqlite3 php8.3-gd \
       php8.3-curl php8.3-mysql php8.3-mbstring \
       php8.3-xml php8.3-zip php8.3-bcmath php8.3-soap \
       php8.3-intl php8.3-readline php8.3-redis \
       php8.3-imagick \
    && curl -sLS https://getcomposer.org/installer | php -- --install-dir=/usr/bin/ --filename=composer \
    && curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg \
    && echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_$NODE_VERSION.x nodistro main" > /etc/apt/sources.list.d/nodesource.list \
    && apt-get update \
    && apt-get install -y nodejs \
    && npm install -g npm \
    && apt-get install -y $MYSQL_CLIENT \
    && apt-get -y autoremove \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

RUN setcap "cap_net_bind_service=+ep" /usr/bin/php8.3

RUN groupadd --force -g $WWWGROUP sail
RUN useradd -ms /bin/bash --no-user-group -g $WWWGROUP -u 1337 sail

COPY . /var/www/html
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/php.ini /etc/php/8.3/cli/conf.d/99-sail.ini

RUN chown -R sail:sail /var/www/html
RUN chmod -R 775 storage
RUN chmod -R 775 bootstrap/cache

USER sail

RUN composer install --no-dev --optimize-autoloader --no-scripts

USER root

RUN echo '#!/bin/bash\n\
php artisan config:cache\n\
php artisan route:cache\n\
php artisan migrate --force\n\
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf' > /start.sh && chmod +x /start.sh

EXPOSE 80

CMD ["/start.sh"]
```

### 3.2 docker-compose.yml
```yaml
services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
      args:
        WWWGROUP: '${WWWGROUP:-1000}'
    image: eventhub/api
    extra_hosts:
      - 'host.docker.internal:host-gateway'
    ports:
      - '${APP_PORT:-80}:80'
    environment:
      WWWUSER: '${WWWUSER:-1000}'
      APP_ENV: '${APP_ENV:-production}'
      APP_DEBUG: '${APP_DEBUG:-false}'
      DB_CONNECTION: mysql
      DB_HOST: mysql
      DB_PORT: 3306
      DB_DATABASE: '${DB_DATABASE}'
      DB_USERNAME: '${DB_USERNAME}'
      DB_PASSWORD: '${DB_PASSWORD}'
      REDIS_HOST: redis
      CACHE_DRIVER: redis
      QUEUE_CONNECTION: redis
      SESSION_DRIVER: redis
    volumes:
      - 'app-storage:/var/www/html/storage'
    networks:
      - eventhub
    depends_on:
      mysql:
        condition: service_healthy
      redis:
        condition: service_started

  queue:
    build:
      context: .
      dockerfile: Dockerfile
    image: eventhub/api
    command: php artisan queue:work --sleep=3 --tries=3 --max-time=3600
    environment:
      APP_ENV: '${APP_ENV:-production}'
      DB_CONNECTION: mysql
      DB_HOST: mysql
      DB_PORT: 3306
      DB_DATABASE: '${DB_DATABASE}'
      DB_USERNAME: '${DB_USERNAME}'
      DB_PASSWORD: '${DB_PASSWORD}'
      REDIS_HOST: redis
      QUEUE_CONNECTION: redis
    volumes:
      - 'app-storage:/var/www/html/storage'
    networks:
      - eventhub
    depends_on:
      - app
      - redis

  mysql:
    image: 'mysql/mysql-server:8.0'
    ports:
      - '${FORWARD_DB_PORT:-3306}:3306'
    environment:
      MYSQL_ROOT_PASSWORD: '${DB_PASSWORD}'
      MYSQL_ROOT_HOST: '%'
      MYSQL_DATABASE: '${DB_DATABASE}'
      MYSQL_USER: '${DB_USERNAME}'
      MYSQL_PASSWORD: '${DB_PASSWORD}'
      MYSQL_ALLOW_EMPTY_PASSWORD: 1
    volumes:
      - 'mysql-data:/var/lib/mysql'
    networks:
      - eventhub
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-p${DB_PASSWORD}"]
      retries: 3
      timeout: 5s

  redis:
    image: 'redis:alpine'
    ports:
      - '${FORWARD_REDIS_PORT:-6379}:6379'
    volumes:
      - 'redis-data:/data'
    networks:
      - eventhub
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      retries: 3
      timeout: 5s

networks:
  eventhub:
    driver: bridge

volumes:
  app-storage:
    driver: local
  mysql-data:
    driver: local
  redis-data:
    driver: local
```

### 3.3 supervisord.conf
```ini
[supervisord]
nodaemon=true
user=root
logfile=/var/log/supervisor/supervisord.log
pidfile=/var/run/supervisord.pid

[program:php]
command=%(ENV_SUPERVISOR_PHP_COMMAND)s
user=%(ENV_SUPERVISOR_PHP_USER)s
environment=LARAVEL_SAIL="1"
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

[program:queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=sail
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/queue.log
stopwaitsecs=3600
```

### 3.4 php.ini
```ini
[PHP]
post_max_size = 100M
upload_max_filesize = 100M
memory_limit = 512M
max_execution_time = 60

[Date]
date.timezone = UTC

[opcache]
opcache.enable = 1
opcache.memory_consumption = 256
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 0
opcache.save_comments = 1
opcache.fast_shutdown = 1
```

---

## 4. Database Schema

### 4.1 Migration: create_categories_table
```php
id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('color', 7)->default('#3b82f6'); // Hex color for UI
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
```

### 4.2 Migration: create_category_user_table
```php
id();
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('permission', ['view', 'edit', 'manage'])->default('view');
            $table->timestamps();

            $table->unique(['category_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_user');
    }
};
```

### 4.3 Migration: create_events_table
```php
id();
            $table->string('slug')->unique();
            $table->foreignId('category_id')->nullable()->constrained()->onDelete('set null');
            
            // Basic Info
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('date');
            $table->time('time');
            $table->string('location');
            
            // Pricing & Inventory
            $table->decimal('price', 10, 2);
            $table->unsignedInteger('max_tickets');
            $table->unsignedInteger('tickets_sold')->default(0);
            
            // Status
            $table->enum('status', ['draft', 'live', 'closed'])->default('draft');
            $table->boolean('registration_open')->default(true);
            $table->dateTime('registration_deadline')->nullable();
            
            // Page Design
            $table->string('hero_title');
            $table->string('hero_subtitle', 500);
            $table->string('hero_image')->nullable(); // S3 path
            $table->text('about');
            
            // Stripe
            $table->string('stripe_product_id')->nullable();
            $table->string('stripe_price_id')->nullable();
            
            // Metadata
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['status', 'registration_open']);
            $table->index('category_id');
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
```

### 4.4 Migration: create_event_items_table
```php
id();
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->unsignedInteger('max_quantity')->nullable(); // null = unlimited
            $table->unsignedInteger('quantity_sold')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('stripe_price_id')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_items');
    }
};
```

### 4.5 Migration: create_orders_table
```php
id();
            $table->string('order_number', 20)->unique();
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            
            // Customer Info
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone')->nullable();
            
            // Status & Payment
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded'])->default('pending');
            $table->decimal('subtotal', 10, 2);
            $table->decimal('total', 10, 2);
            
            // Stripe
            $table->string('stripe_checkout_session_id')->nullable();
            $table->string('stripe_payment_intent_id')->nullable();
            
            // Timestamps
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('order_number');
            $table->index(['event_id', 'status']);
            $table->index('customer_email');
            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
```

### 4.6 Migration: create_order_items_table
```php
id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->enum('item_type', ['ticket', 'extra_item']);
            $table->unsignedBigInteger('item_id')->nullable(); // For extra_items, references event_items.id
            $table->string('item_name');
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_price', 10, 2);
            $table->timestamps();

            $table->index(['order_id', 'item_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
```

### 4.7 Migration: create_activity_logs_table
```php
id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('action'); // created, updated, deleted, published, etc.
            $table->string('model_type'); // App\Models\Event, App\Models\Order, etc.
            $table->unsignedBigInteger('model_id');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['model_type', 'model_id']);
            $table->index('user_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
```

### 4.8 Migration: update_users_table
```php
string('google_id')->nullable()->after('password');
            $table->string('avatar')->nullable()->after('google_id');
            $table->enum('role', ['super_admin', 'admin', 'viewer'])->default('viewer')->after('avatar');
            $table->boolean('is_active')->default(true)->after('role');

            $table->index('google_id');
            $table->index(['role', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['google_id', 'avatar', 'role', 'is_active']);
        });
    }
};
```

### 4.9 Seeders

#### DatabaseSeeder.php
```php
call([
            AdminUserSeeder::class,
            CategorySeeder::class,
            // DemoEventSeeder::class, // Optional: for testing
        ]);
    }
}
```

#### AdminUserSeeder.php
```php
<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@eventhub.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'), // Change in production!
                'role' => 'super_admin',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
    }
}
```

#### CategorySeeder.php
```php
<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', 'super_admin')->first();

        $categories = [
            ['name' => 'Primaria', 'color' => '#22c55e'],
            ['name' => 'Secundaria', 'color' => '#3b82f6'],
            ['name' => 'Preparatoria', 'color' => '#8b5cf6'],
            ['name' => 'General', 'color' => '#6b7280'],
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(
                ['slug' => Str::slug($category['name'])],
                [
                    'name' => $category['name'],
                    'slug' => Str::slug($category['name']),
                    'color' => $category['color'],
                    'created_by' => $admin->id,
                ]
            );
        }
    }
}
```

---

## 5. Authentication System

### 5.1 Fortify Configuration

#### config/fortify.php
```php
<?php

return [
    'guard' => 'web',
    'middleware' => ['web'],
    'auth_middleware' => 'auth',
    'passwords' => 'users',
    'username' => 'email',
    'email' => 'email',
    'home' => '/dashboard',
    'prefix' => '',
    'domain' => null,
    'lowercase_usernames' => true,
    'limiters' => [
        'login' => 'login',
        'two-factor' => 'two-factor',
    ],
    'views' => false, // API only, no views
    'features' => [
        Features::resetPasswords(),
        Features::emailVerification(),
        Features::updateProfileInformation(),
        Features::updatePasswords(),
    ],
];
```

#### app/Providers/FortifyServiceProvider.php
```php
<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Fortify::authenticateUsing(function (Request $request) {
            $user = User::where('email', $request->email)->first();

            if ($user &&
                $user->is_active &&
                Hash::check($request->password, $user->password)) {
                return $user;
            }
        });
    }
}
```

### 5.2 Sanctum Configuration

#### config/sanctum.php
```php
<?php

return [
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
        env('APP_URL') ? ','.parse_url(env('APP_URL'), PHP_URL_HOST) : ''
    ))),

    'guard' => ['web'],

    'expiration' => 60 * 24, // 24 hours

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],
];
```

### 5.3 Auth Controllers

#### AuthController.php
```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login with email and password
     * POST /api/auth/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Your account has been deactivated.'],
            ]);
        }

        // Revoke existing tokens
        $user->tokens()->delete();

        // Create new token
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
        ]);
    }

    /**
     * Logout - revoke current token
     * POST /api/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Get authenticated user
     * GET /api/auth/user
     */
    public function user(Request $request): JsonResponse
    {
        $user = $request->user()->load('categories');

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Send password reset link
     * POST /api/auth/forgot-password
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json([
            'message' => 'Password reset link sent to your email.',
        ]);
    }

    /**
     * Reset password with token
     * POST /api/auth/reset-password
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json([
            'message' => 'Password has been reset.',
        ]);
    }
}
```

#### GoogleAuthController.php
```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    /**
     * Redirect to Google OAuth
     * GET /api/auth/google/redirect
     */
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle Google OAuth callback
     * GET /api/auth/google/callback
     */
    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();

            $user = User::where('google_id', $googleUser->id)
                ->orWhere('email', $googleUser->email)
                ->first();

            if (!$user) {
                // User doesn't exist - redirect with error
                // Only existing users can login via Google
                return redirect(config('app.frontend_url') . '/login?error=user_not_found');
            }

            if (!$user->is_active) {
                return redirect(config('app.frontend_url') . '/login?error=account_deactivated');
            }

            // Update Google ID if not set
            if (!$user->google_id) {
                $user->update([
                    'google_id' => $googleUser->id,
                    'avatar' => $googleUser->avatar,
                ]);
            }

            // Revoke existing tokens and create new one
            $user->tokens()->delete();
            $token = $user->createToken('auth-token')->plainTextToken;

            // Redirect to frontend with token
            return redirect(config('app.frontend_url') . '/auth/callback?token=' . $token);

        } catch (\Exception $e) {
            return redirect(config('app.frontend_url') . '/login?error=oauth_failed');
        }
    }
}
```

### 5.4 Auth Request Validation

#### LoginRequest.php
```php
<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email',
            'password' => 'required|string',
        ];
    }
}
```

---

## 6. API Endpoints

### 6.1 Routes Overview

#### routes/api.php
```php
<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GoogleAuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\EventItemController;
use App\Http\Controllers\Api\PublicEventController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ReportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes (No Authentication Required)
|--------------------------------------------------------------------------
*/

// Authentication
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    
    // Google OAuth
    Route::get('/google/redirect', [GoogleAuthController::class, 'redirect']);
    Route::get('/google/callback', [GoogleAuthController::class, 'callback']);
});

// Public Event Pages
Route::prefix('public')->group(function () {
    Route::get('/events', [PublicEventController::class, 'index']);
    Route::get('/events/{slug}', [PublicEventController::class, 'show']);
    Route::get('/events/{slug}/availability', [PublicEventController::class, 'availability']);
});

// Checkout (public but requires event to be live)
Route::post('/checkout/create-session', [CheckoutController::class, 'createSession']);

// Stripe Webhooks
Route::post('/webhooks/stripe', [WebhookController::class, 'handleStripe']);

/*
|--------------------------------------------------------------------------
| Protected Routes (Authentication Required)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    
    // Auth
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'user']);
    });

    // Dashboard
    Route::prefix('dashboard')->group(function () {
        Route::get('/stats', [DashboardController::class, 'stats']);
        Route::get('/events/{slug}/stats', [DashboardController::class, 'eventStats']);
    });

    // Categories
    Route::apiResource('categories', CategoryController::class);

    // Events
    Route::prefix('events')->group(function () {
        Route::get('/', [EventController::class, 'index']);
        Route::post('/', [EventController::class, 'store']);
        Route::get('/{slug}', [EventController::class, 'show']);
        Route::put('/{slug}', [EventController::class, 'update']);
        Route::delete('/{slug}', [EventController::class, 'destroy']);
        
        // Event Actions
        Route::post('/{slug}/publish', [EventController::class, 'publish']);
        Route::post('/{slug}/close', [EventController::class, 'close']);
        Route::post('/{slug}/toggle-registration', [EventController::class, 'toggleRegistration']);
        Route::post('/{slug}/duplicate', [EventController::class, 'duplicate']);
        Route::post('/{slug}/hero-image', [EventController::class, 'uploadHeroImage']);
        
        // Event Items
        Route::get('/{slug}/items', [EventItemController::class, 'index']);
        Route::post('/{slug}/items', [EventItemController::class, 'store']);
        Route::put('/{slug}/items/{itemId}', [EventItemController::class, 'update']);
        Route::delete('/{slug}/items/{itemId}', [EventItemController::class, 'destroy']);
        
        // Event Orders
        Route::get('/{slug}/orders', [EventController::class, 'orders']);
    });

    // Users (Super Admin only)
    Route::middleware('can:manage-users')->group(function () {
        Route::apiResource('users', UserController::class);
        Route::post('/users/{id}/categories', [UserController::class, 'assignCategories']);
        Route::post('/users/{id}/toggle-active', [UserController::class, 'toggleActive']);
    });

    // Reports
    Route::prefix('reports')->group(function () {
        Route::get('/sales', [ReportController::class, 'sales']);
        Route::get('/sales/export', [ReportController::class, 'exportSales']);
        Route::get('/orders', [ReportController::class, 'orders']);
        Route::get('/orders/export', [ReportController::class, 'exportOrders']);
    });

    // Orders
    Route::get('/orders/{orderNumber}', [CheckoutController::class, 'showOrder']);
});
```

### 6.2 Endpoint Details

#### Public Events

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| GET | `/api/public/events` | List all live events | No |
| GET | `/api/public/events/{slug}` | Get single event details | No |
| GET | `/api/public/events/{slug}/availability` | Check ticket/item availability | No |

#### Authentication

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| POST | `/api/auth/login` | Email/password login | No |
| POST | `/api/auth/logout` | Revoke token | Yes |
| GET | `/api/auth/user` | Get current user | Yes |
| POST | `/api/auth/forgot-password` | Send reset email | No |
| POST | `/api/auth/reset-password` | Reset with token | No |
| GET | `/api/auth/google/redirect` | Redirect to Google | No |
| GET | `/api/auth/google/callback` | Handle Google callback | No |

#### Categories

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| GET | `/api/categories` | List categories | Yes |
| POST | `/api/categories` | Create category | Yes (super_admin) |
| GET | `/api/categories/{id}` | Get category | Yes |
| PUT | `/api/categories/{id}` | Update category | Yes (super_admin) |
| DELETE | `/api/categories/{id}` | Delete category | Yes (super_admin) |

#### Events

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| GET | `/api/events` | List events (filtered by user access) | Yes |
| POST | `/api/events` | Create event | Yes (edit permission) |
| GET | `/api/events/{slug}` | Get event details | Yes |
| PUT | `/api/events/{slug}` | Update event | Yes (edit permission) |
| DELETE | `/api/events/{slug}` | Soft delete event | Yes (manage permission) |
| POST | `/api/events/{slug}/publish` | Set status to live | Yes (edit permission) |
| POST | `/api/events/{slug}/close` | Set status to closed | Yes (edit permission) |
| POST | `/api/events/{slug}/toggle-registration` | Toggle registration_open | Yes (edit permission) |
| POST | `/api/events/{slug}/duplicate` | Clone event | Yes (edit permission) |
| POST | `/api/events/{slug}/hero-image` | Upload hero image | Yes (edit permission) |
| GET | `/api/events/{slug}/orders` | List event orders | Yes |

#### Event Items

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| GET | `/api/events/{slug}/items` | List event items | Yes |
| POST | `/api/events/{slug}/items` | Create item | Yes (edit permission) |
| PUT | `/api/events/{slug}/items/{id}` | Update item | Yes (edit permission) |
| DELETE | `/api/events/{slug}/items/{id}` | Delete item | Yes (edit permission) |

#### Checkout

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| POST | `/api/checkout/create-session` | Create Stripe Checkout | No |
| GET | `/api/orders/{orderNumber}` | Get order details | Yes |
| POST | `/api/webhooks/stripe` | Stripe webhook handler | No (signature verified) |

#### Users

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| GET | `/api/users` | List users | Yes (super_admin) |
| POST | `/api/users` | Create user | Yes (super_admin) |
| GET | `/api/users/{id}` | Get user | Yes (super_admin) |
| PUT | `/api/users/{id}` | Update user | Yes (super_admin) |
| DELETE | `/api/users/{id}` | Deactivate user | Yes (super_admin) |
| POST | `/api/users/{id}/categories` | Assign categories | Yes (super_admin) |
| POST | `/api/users/{id}/toggle-active` | Toggle active status | Yes (super_admin) |

#### Dashboard & Reports

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| GET | `/api/dashboard/stats` | Overall statistics | Yes |
| GET | `/api/dashboard/events/{slug}/stats` | Event statistics | Yes |
| GET | `/api/reports/sales` | Sales report | Yes |
| GET | `/api/reports/sales/export` | Export sales to Excel | Yes |
| GET | `/api/reports/orders` | Orders list | Yes |
| GET | `/api/reports/orders/export` | Export orders to Excel | Yes |

---

## 7. Business Logic & Services

### 7.1 Models

#### User.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'avatar',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    // Relationships

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)
            ->withPivot('permission')
            ->withTimestamps();
    }

    public function createdEvents(): HasMany
    {
        return $this->hasMany(Event::class, 'created_by');
    }

    public function createdCategories(): HasMany
    {
        return $this->hasMany(Category::class, 'created_by');
    }

    // Helper Methods

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function hasAccessToCategory(int $categoryId): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->categories()->where('category_id', $categoryId)->exists();
    }

    public function getCategoryPermission(int $categoryId): ?string
    {
        if ($this->isSuperAdmin()) {
            return 'manage';
        }

        $category = $this->categories()->where('category_id', $categoryId)->first();
        return $category?->pivot?->permission;
    }

    public function canEditCategory(int $categoryId): bool
    {
        $permission = $this->getCategoryPermission($categoryId);
        return in_array($permission, ['edit', 'manage']);
    }

    public function canManageCategory(int $categoryId): bool
    {
        return $this->getCategoryPermission($categoryId) === 'manage';
    }
}
```

#### Category.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'color',
        'created_by',
    ];

    // Relationships

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('permission')
            ->withTimestamps();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes

    public function scopeAccessibleBy($query, User $user)
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        return $query->whereHas('users', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        });
    }
}
```

#### Event.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Event extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'slug',
        'category_id',
        'name',
        'description',
        'date',
        'time',
        'location',
        'price',
        'max_tickets',
        'tickets_sold',
        'status',
        'registration_open',
        'registration_deadline',
        'hero_title',
        'hero_subtitle',
        'hero_image',
        'about',
        'stripe_product_id',
        'stripe_price_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'price' => 'decimal:2',
            'max_tickets' => 'integer',
            'tickets_sold' => 'integer',
            'registration_open' => 'boolean',
            'registration_deadline' => 'datetime',
        ];
    }

    // Auto-generate slug on create
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($event) {
            if (empty($event->slug)) {
                $event->slug = static::generateUniqueSlug($event->name);
            }
        });
    }

    public static function generateUniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $counter = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    // Relationships

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(EventItem::class);
    }

    public function activeItems(): HasMany
    {
        return $this->hasMany(EventItem::class)->where('is_active', true);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function completedOrders(): HasMany
    {
        return $this->hasMany(Order::class)->where('status', 'completed');
    }

    // Business Logic

    public function canPurchase(): bool
    {
        // Must be live
        if ($this->status !== 'live') {
            return false;
        }

        // Must be manually open
        if (!$this->registration_open) {
            return false;
        }

        // Must not be past deadline (if set)
        if ($this->registration_deadline && now()->isAfter($this->registration_deadline)) {
            return false;
        }

        // Must have tickets available
        if ($this->tickets_sold >= $this->max_tickets) {
            return false;
        }

        return true;
    }

    public function getPurchaseBlockedReason(): ?string
    {
        if ($this->status !== 'live') {
            return 'not_live';
        }

        if (!$this->registration_open) {
            return 'registration_closed';
        }

        if ($this->registration_deadline && now()->isAfter($this->registration_deadline)) {
            return 'deadline_passed';
        }

        if ($this->tickets_sold >= $this->max_tickets) {
            return 'sold_out';
        }

        return null;
    }

    public function getTicketsAvailable(): int
    {
        return max(0, $this->max_tickets - $this->tickets_sold);
    }

    public function getRevenue(): float
    {
        return $this->completedOrders()->sum('total');
    }

    // Scopes

    public function scopeLive($query)
    {
        return $query->where('status', 'live');
    }

    public function scopeAccessibleBy($query, User $user)
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        $categoryIds = $user->categories()->pluck('categories.id');

        return $query->whereIn('category_id', $categoryIds);
    }
}
```

#### EventItem.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'name',
        'description',
        'price',
        'max_quantity',
        'quantity_sold',
        'is_active',
        'stripe_price_id',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'max_quantity' => 'integer',
            'quantity_sold' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    // Relationships

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    // Business Logic

    public function getAvailableQuantity(): ?int
    {
        if ($this->max_quantity === null) {
            return null; // Unlimited
        }

        return max(0, $this->max_quantity - $this->quantity_sold);
    }

    public function isAvailable(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->max_quantity === null) {
            return true;
        }

        return $this->quantity_sold < $this->max_quantity;
    }
}
```

#### Order.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'event_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'status',
        'subtotal',
        'total',
        'stripe_checkout_session_id',
        'stripe_payment_intent_id',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            if (empty($order->order_number)) {
                $order->order_number = static::generateOrderNumber();
            }
        });
    }

    public static function generateOrderNumber(): string
    {
        $prefix = 'ORD';
        $timestamp = now()->format('ymd');
        $random = strtoupper(Str::random(4));

        $orderNumber = "{$prefix}-{$timestamp}-{$random}";

        // Ensure uniqueness
        while (static::where('order_number', $orderNumber)->exists()) {
            $random = strtoupper(Str::random(4));
            $orderNumber = "{$prefix}-{$timestamp}-{$random}";
        }

        return $orderNumber;
    }

    // Relationships

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    // Helper Methods

    public function getTicketCount(): int
    {
        return $this->items()
            ->where('item_type', 'ticket')
            ->sum('quantity');
    }

    public function markAsCompleted(string $paymentIntentId): void
    {
        $this->update([
            'status' => 'completed',
            'stripe_payment_intent_id' => $paymentIntentId,
            'paid_at' => now(),
        ]);
    }

    public function markAsFailed(): void
    {
        $this->update(['status' => 'failed']);
    }

    public function markAsRefunded(): void
    {
        $this->update(['status' => 'refunded']);
    }
}
```

#### OrderItem.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'item_type',
        'item_id',
        'item_name',
        'quantity',
        'unit_price',
        'total_price',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
        ];
    }

    // Relationships

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function eventItem(): BelongsTo
    {
        return $this->belongsTo(EventItem::class, 'item_id');
    }
}
```

#### ActivityLog.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'action',
        'model_type',
        'model_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    // Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo('model');
    }

    // Static Methods

    public static function log(
        string $action,
        Model $model,
        ?array $oldValues = null,
        ?array $newValues = null
    ): self {
        return static::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'model_type' => get_class($model),
            'model_id' => $model->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
```

### 7.2 Services

#### StripeService.php
```php
<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventItem;
use App\Models\Order;
use Stripe\Checkout\Session;
use Stripe\Price;
use Stripe\Product;
use Stripe\Stripe;

class StripeService
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * Create Stripe Product and Price for an event
     */
    public function createEventProduct(Event $event): array
    {
        // Create Product
        $product = Product::create([
            'name' => $event->name,
            'description' => $event->about,
            'metadata' => [
                'event_id' => $event->id,
                'event_slug' => $event->slug,
            ],
        ]);

        // Create Price
        $price = Price::create([
            'product' => $product->id,
            'unit_amount' => (int) ($event->price * 100), // Convert to cents
            'currency' => 'usd',
        ]);

        return [
            'product_id' => $product->id,
            'price_id' => $price->id,
        ];
    }

    /**
     * Create Stripe Price for an event item
     */
    public function createItemPrice(EventItem $item, string $eventProductId): string
    {
        $price = Price::create([
            'product' => $eventProductId,
            'unit_amount' => (int) ($item->price * 100),
            'currency' => 'usd',
            'nickname' => $item->name,
            'metadata' => [
                'item_id' => $item->id,
                'item_type' => 'extra_item',
            ],
        ]);

        return $price->id;
    }

    /**
     * Create Checkout Session
     */
    public function createCheckoutSession(
        Event $event,
        Order $order,
        array $lineItems,
        string $successUrl,
        string $cancelUrl
    ): Session {
        return Session::create([
            'mode' => 'payment',
            'success_url' => $successUrl . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl,
            'customer_email' => $order->customer_email,
            'line_items' => $lineItems,
            'metadata' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'event_id' => $event->id,
            ],
            'payment_intent_data' => [
                'metadata' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                ],
            ],
        ]);
    }

    /**
     * Build line items array for checkout
     */
    public function buildLineItems(Event $event, int $ticketQuantity, array $extraItems = []): array
    {
        $lineItems = [];

        // Add tickets
        if ($ticketQuantity > 0) {
            $lineItems[] = [
                'price' => $event->stripe_price_id,
                'quantity' => $ticketQuantity,
            ];
        }

        // Add extra items
        foreach ($extraItems as $extraItem) {
            $item = EventItem::find($extraItem['item_id']);
            if ($item && $item->stripe_price_id) {
                $lineItems[] = [
                    'price' => $item->stripe_price_id,
                    'quantity' => $extraItem['quantity'],
                ];
            }
        }

        return $lineItems;
    }

    /**
     * Verify webhook signature
     */
    public function constructWebhookEvent(string $payload, string $signature): \Stripe\Event
    {
        return \Stripe\Webhook::constructEvent(
            $payload,
            $signature,
            config('services.stripe.webhook_secret')
        );
    }
}
```

#### ImageService.php
```php
manager = new ImageManager(new Driver());
    }

    /**
     * Upload and resize hero image
     */
    public function uploadHeroImage(UploadedFile $file, string $eventSlug): string
    {
        // Read and resize image
        $image = $this->manager->read($file->getContent());
        $image->scaleDown(width: 1920, height: 1080);

        // Generate filename
        $filename = sprintf(
            'events/%s/hero-%s.%s',
            $eventSlug,
            Str::random(8),
            $file->getClientOriginalExtension()
        );

        // Upload to S3
        Storage::disk($this->disk)->put(
            $filename,
            $image->toJpeg(quality: 85),
            'public'
        );

        return $filename;
    }

    /**
     * Delete image from S3
     */
    public function deleteImage(string $path): bool
    {
        if (Storage::disk($this->disk)->exists($path)) {
            return Storage::disk($this->disk)->delete($path);
        }

        return false;
    }

    /**
     * Get full URL for image
     */
    public function getUrl(string $path): string
    {
        return Storage::disk($this->disk)->url($path);
    }
}
```

#### ReportService.php
```php
<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * Get overall dashboard statistics
     */
    public function getDashboardStats(User $user): array
    {
        $eventsQuery = Event::accessibleBy($user);

        $totalEvents = (clone $eventsQuery)->count();
        $liveEvents = (clone $eventsQuery)->where('status', 'live')->count();

        $eventIds = (clone $eventsQuery)->pluck('id');

        $ordersQuery = Order::whereIn('event_id', $eventIds)
            ->where('status', 'completed');

        $totalOrders = (clone $ordersQuery)->count();
        $totalRevenue = (clone $ordersQuery)->sum('total');

        $todayOrders = Order::whereIn('event_id', $eventIds)
            ->where('status', 'completed')
            ->whereDate('paid_at', today());

        $ticketsSoldToday = $todayOrders->count();
        $revenueToday = (clone $todayOrders)->sum('total');

        return [
            'total_events' => $totalEvents,
            'live_events' => $liveEvents,
            'total_orders' => $totalOrders,
            'total_revenue' => round($totalRevenue, 2),
            'tickets_sold_today' => $ticketsSoldToday,
            'revenue_today' => round($revenueToday, 2),
        ];
    }

    /**
     * Get statistics for a specific event
     */
    public function getEventStats(Event $event): array
    {
        $completedOrders = $event->completedOrders();

        $ordersCount = $completedOrders->count();
        $revenue = $completedOrders->sum('total');

        // Sales by day (last 30 days)
        $salesByDay = Order::where('event_id', $event->id)
            ->where('status', 'completed')
            ->where('paid_at', '>=', now()->subDays(30))
            ->select(
                DB::raw('DATE(paid_at) as date'),
                DB::raw('COUNT(*) as orders'),
                DB::raw('SUM(total) as revenue')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Extra items sold
        $extraItemsSold = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.event_id', $event->id)
            ->where('orders.status', 'completed')
            ->where('order_items.item_type', 'extra_item')
            ->select(
                'order_items.item_name as name',
                DB::raw('SUM(order_items.quantity) as quantity'),
                DB::raw('SUM(order_items.total_price) as revenue')
            )
            ->groupBy('order_items.item_name')
            ->get();

        return [
            'event' => $event,
            'tickets_sold' => $event->tickets_sold,
            'tickets_available' => $event->getTicketsAvailable(),
            'revenue' => round($revenue, 2),
            'orders_count' => $ordersCount,
            'sales_by_day' => $salesByDay,
            'extra_items_sold' => $extraItemsSold,
        ];
    }

    /**
     * Get sales report data
     */
    public function getSalesReport(User $user, array $filters = []): Collection
    {
        $eventIds = Event::accessibleBy($user)->pluck('id');

        $query = Order::whereIn('event_id', $eventIds)
            ->where('status', 'completed')
            ->with('event:id,name,slug,category_id', 'event.category:id,name');

        // Apply filters
        if (!empty($filters['event_id'])) {
            $query->where('event_id', $filters['event_id']);
        }

        if (!empty($filters['category_id'])) {
            $query->whereHas('event', function ($q) use ($filters) {
                $q->where('category_id', $filters['category_id']);
            });
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('paid_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('paid_at', '<=', $filters['date_to']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_email', 'like', "%{$search}%")
                    ->orWhere('order_number', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('paid_at', 'desc')->get();
    }
}
```

---

## 8. Stripe Integration

### 8.1 Checkout Controller
```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCheckoutRequest;
use App\Models\Event;
use App\Models\EventItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    public function __construct(
        private StripeService $stripeService
    ) {}

    /**
     * Create Stripe Checkout Session
     * POST /api/checkout/create-session
     */
    public function createSession(CreateCheckoutRequest $request): JsonResponse
    {
        $event = Event::where('slug', $request->event_slug)->firstOrFail();

        // Validate event can accept purchases
        if (!$event->canPurchase()) {
            return response()->json([
                'error' => 'Cannot purchase tickets',
                'reason' => $event->getPurchaseBlockedReason(),
            ], 422);
        }

        // Validate ticket availability
        $requestedTickets = $request->tickets;
        if ($requestedTickets > $event->getTicketsAvailable()) {
            return response()->json([
                'error' => 'Not enough tickets available',
                'available' => $event->getTicketsAvailable(),
            ], 422);
        }

        // Validate extra items availability
        $extraItems = $request->extra_items ?? [];
        foreach ($extraItems as $extraItem) {
            $item = EventItem::find($extraItem['item_id']);
            if (!$item || !$item->isAvailable()) {
                return response()->json([
                    'error' => "Item '{$item?->name}' is not available",
                ], 422);
            }

            $available = $item->getAvailableQuantity();
            if ($available !== null && $extraItem['quantity'] > $available) {
                return response()->json([
                    'error' => "Not enough '{$item->name}' available",
                    'available' => $available,
                ], 422);
            }
        }

        // Calculate totals
        $subtotal = $event->price * $requestedTickets;
        foreach ($extraItems as $extraItem) {
            $item = EventItem::find($extraItem['item_id']);
            $subtotal += $item->price * $extraItem['quantity'];
        }
        $total = $subtotal; // Add fees/taxes if needed

        // Create order in database
        $order = DB::transaction(function () use ($event, $request, $requestedTickets, $extraItems, $subtotal, $total) {
            $order = Order::create([
                'event_id' => $event->id,
                'customer_name' => $request->customer_name,
                'customer_email' => $request->customer_email,
                'customer_phone' => $request->customer_phone,
                'status' => 'pending',
                'subtotal' => $subtotal,
                'total' => $total,
            ]);

            // Add ticket line item
            if ($requestedTickets > 0) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'item_type' => 'ticket',
                    'item_name' => $event->name . ' - Ticket',
                    'quantity' => $requestedTickets,
                    'unit_price' => $event->price,
                    'total_price' => $event->price * $requestedTickets,
                ]);
            }

            // Add extra item line items
            foreach ($extraItems as $extraItem) {
                $item = EventItem::find($extraItem['item_id']);
                OrderItem::create([
                    'order_id' => $order->id,
                    'item_type' => 'extra_item',
                    'item_id' => $item->id,
                    'item_name' => $item->name,
                    'quantity' => $extraItem['quantity'],
                    'unit_price' => $item->price,
                    'total_price' => $item->price * $extraItem['quantity'],
                ]);
            }

            return $order;
        });

        // Build Stripe line items
        $lineItems = $this->stripeService->buildLineItems($event, $requestedTickets, $extraItems);

        // Create Stripe Checkout Session
        $successUrl = config('app.frontend_url') . "/app/events/{$event->slug}/checkout-success";
        $cancelUrl = config('app.frontend_url') . "/app/events/{$event->slug}";

        $session = $this->stripeService->createCheckoutSession(
            $event,
            $order,
            $lineItems,
            $successUrl,
            $cancelUrl
        );

        // Update order with session ID
        $order->update([
            'stripe_checkout_session_id' => $session->id,
        ]);

        return response()->json([
            'checkout_url' => $session->url,
            'session_id' => $session->id,
            'order_number' => $order->order_number,
        ]);
    }

    /**
     * Get order details
     * GET /api/orders/{orderNumber}
     */
    public function showOrder(string $orderNumber): JsonResponse
    {
        $order = Order::where('order_number', $orderNumber)
            ->with(['event:id,name,slug,date,time,location', 'items'])
            ->firstOrFail();

        return response()->json([
            'order' => $order,
        ]);
    }
}
```

### 8.2 Webhook Controller
```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\OrderConfirmation;
use App\Models\Event;
use App\Models\EventItem;
use App\Models\Order;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class WebhookController extends Controller
{
    public function __construct(
        private StripeService $stripeService
    ) {}

    /**
     * Handle Stripe webhooks
     * POST /api/webhooks/stripe
     */
    public function handleStripe(Request $request): Response
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        try {
            $event = $this->stripeService->constructWebhookEvent($payload, $signature);
        } catch (\Exception $e) {
            Log::error('Stripe webhook signature verification failed', [
                'error' => $e->getMessage(),
            ]);
            return response('Invalid signature', 400);
        }

        Log::info('Stripe webhook received', [
            'type' => $event->type,
            'id' => $event->id,
        ]);

        match ($event->type) {
            'checkout.session.completed' => $this->handleCheckoutCompleted($event->data->object),
            'payment_intent.payment_failed' => $this->handlePaymentFailed($event->data->object),
            'charge.refunded' => $this->handleRefund($event->data->object),
            default => null,
        };

        return response('OK', 200);
    }

    private function handleCheckoutCompleted(object $session): void
    {
        $orderId = $session->metadata->order_id ?? null;

        if (!$orderId) {
            Log::warning('Checkout completed but no order_id in metadata', [
                'session_id' => $session->id,
            ]);
            return;
        }

        $order = Order::find($orderId);

        if (!$order) {
            Log::warning('Order not found for checkout session', [
                'order_id' => $orderId,
            ]);
            return;
        }

        DB::transaction(function () use ($order, $session) {
            // Mark order as completed
            $order->markAsCompleted($session->payment_intent);

            // Increment tickets sold
            $ticketItem = $order->items()->where('item_type', 'ticket')->first();
            if ($ticketItem) {
                Event::where('id', $order->event_id)
                    ->increment('tickets_sold', $ticketItem->quantity);
            }

            // Increment extra items sold
            $extraItems = $order->items()->where('item_type', 'extra_item')->get();
            foreach ($extraItems as $orderItem) {
                EventItem::where('id', $orderItem->item_id)
                    ->increment('quantity_sold', $orderItem->quantity);
            }
        });

        // Send confirmation email
        Mail::to($order->customer_email)->send(new OrderConfirmation($order));

        Log::info('Order completed', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
        ]);
    }

    private function handlePaymentFailed(object $paymentIntent): void
    {
        $orderId = $paymentIntent->metadata->order_id ?? null;

        if (!$orderId) {
            return;
        }

        $order = Order::find($orderId);
        $order?->markAsFailed();

        Log::info('Payment failed', [
            'order_id' => $orderId,
        ]);
    }

    private function handleRefund(object $charge): void
    {
        $order = Order::where('stripe_payment_intent_id', $charge->payment_intent)->first();

        if (!$order || $order->status === 'refunded') {
            return;
        }

        DB::transaction(function () use ($order) {
            $order->markAsRefunded();

            // Decrement tickets sold
            $ticketItem = $order->items()->where('item_type', 'ticket')->first();
            if ($ticketItem) {
                Event::where('id', $order->event_id)
                    ->decrement('tickets_sold', $ticketItem->quantity);
            }

            // Decrement extra items sold
            $extraItems = $order->items()->where('item_type', 'extra_item')->get();
            foreach ($extraItems as $orderItem) {
                EventItem::where('id', $orderItem->item_id)
                    ->decrement('quantity_sold', $orderItem->quantity);
            }
        });

        Log::info('Order refunded', [
            'order_id' => $order->id,
        ]);
    }
}
```

### 8.3 Checkout Request Validation
```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Public endpoint
    }

    public function rules(): array
    {
        return [
            'event_slug' => 'required|string|exists:events,slug',
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'tickets' => 'required|integer|min:1|max:10',
            'extra_items' => 'nullable|array',
            'extra_items.*.item_id' => 'required|integer|exists:event_items,id',
            'extra_items.*.quantity' => 'required|integer|min:1|max:10',
        ];
    }
}
```

---

## 9. Email Notifications

### 9.1 Order Confirmation Mail
```php
order->load('event', 'items');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Order Confirmed - {$this->order->event->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.orders.confirmation',
            with: [
                'order' => $this->order,
                'event' => $this->order->event,
                'ticketCount' => $this->order->getTicketCount(),
            ],
        );
    }
}
```

### 9.2 Email Template

#### resources/views/emails/orders/confirmation.blade.php
```blade
<x-mail::message>
# Order Confirmed! 🎉

Thank you for your purchase, {{ $order->customer_name }}!

## Order Details

**Order Number:** {{ $order->order_number }}

**Event:** {{ $event->name }}

**Date:** {{ $event->date->format('F j, Y') }} at {{ $event->time }}

**Location:** {{ $event->location }}

---

## Items Purchased

<x-mail::table>
| Item | Qty | Price |
|:-----|:---:|------:|
@foreach($order->items as $item)
| {{ $item->item_name }} | {{ $item->quantity }} | ${{ number_format($item->total_price, 2) }} |
@endforeach
| **Total** | | **${{ number_format($order->total, 2) }}** |
</x-mail::table>

---

Please save this email as your receipt. You may be asked to show it at the event.

If you have any questions, please reply to this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
```

---

## 10. File Uploads (S3)

### 10.1 S3 Configuration

#### config/filesystems.php
```php
<?php

return [
    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'throw' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'visibility' => 'public',
        ],
    ],
];
```

### 10.2 Image Upload in Event Controller
```php
/**
 * Upload hero image
 * POST /api/events/{slug}/hero-image
 */
public function uploadHeroImage(Request $request, string $slug): JsonResponse
{
    $event = Event::where('slug', $slug)->firstOrFail();

    $this->authorize('update', $event);

    $request->validate([
        'image' => 'required|image|mimes:jpeg,png,webp|max:5120', // 5MB max
    ]);

    $imageService = app(ImageService::class);

    // Delete old image if exists
    if ($event->hero_image) {
        $imageService->deleteImage($event->hero_image);
    }

    // Upload new image
    $path = $imageService->uploadHeroImage($request->file('image'), $event->slug);

    $event->update(['hero_image' => $path]);

    return response()->json([
        'message' => 'Image uploaded successfully',
        'path' => $path,
        'url' => $imageService->getUrl($path),
    ]);
}
```

---

## 11. Reports & Excel Export

### 11.1 Report Controller
```php
<?php

namespace App\Http\Controllers\Api;

use App\Exports\OrdersExport;
use App\Exports\SalesExport;
use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function __construct(
        private ReportService $reportService
    ) {}

    /**
     * Get sales report
     * GET /api/reports/sales
     */
    public function sales(Request $request): JsonResponse
    {
        $filters = $request->only([
            'event_id',
            'category_id',
            'date_from',
            'date_to',
            'search',
        ]);

        $orders = $this->reportService->getSalesReport($request->user(), $filters);

        return response()->json([
            'orders' => $orders,
            'summary' => [
                'total_orders' => $orders->count(),
                'total_revenue' => $orders->sum('total'),
            ],
        ]);
    }

    /**
     * Export sales to Excel
     * GET /api/reports/sales/export
     */
    public function exportSales(Request $request): BinaryFileResponse
    {
        $filters = $request->only([
            'event_id',
            'category_id',
            'date_from',
            'date_to',
            'search',
        ]);

        $filename = 'sales-report-' . now()->format('Y-m-d') . '.xlsx';

        return Excel::download(
            new SalesExport($request->user(), $filters),
            $filename
        );
    }

    /**
     * Get orders report
     * GET /api/reports/orders
     */
    public function orders(Request $request): JsonResponse
    {
        $filters = $request->only([
            'event_id',
            'category_id',
            'date_from',
            'date_to',
            'status',
            'search',
        ]);

        $orders = $this->reportService->getSalesReport($request->user(), $filters);

        return response()->json([
            'orders' => $orders,
        ]);
    }

    /**
     * Export orders to Excel
     * GET /api/reports/orders/export
     */
    public function exportOrders(Request $request): BinaryFileResponse
    {
        $filters = $request->only([
            'event_id',
            'category_id',
            'date_from',
            'date_to',
            'status',
            'search',
        ]);

        $filename = 'orders-report-' . now()->format('Y-m-d') . '.xlsx';

        return Excel::download(
            new OrdersExport($request->user(), $filters),
            $filename
        );
    }
}
```

### 11.2 Sales Export Class
```php
getSalesReport($this->user, $this->filters);
    }

    public function headings(): array
    {
        return [
            'Order Number',
            'Date',
            'Event',
            'Category',
            'Customer Name',
            'Customer Email',
            'Tickets',
            'Total',
            'Status',
        ];
    }

    public function map($order): array
    {
        return [
            $order->order_number,
            $order->paid_at?->format('Y-m-d H:i'),
            $order->event->name,
            $order->event->category?->name ?? 'N/A',
            $order->customer_name,
            $order->customer_email,
            $order->getTicketCount(),
            '$' . number_format($order->total, 2),
            ucfirst($order->status),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
```

### 11.3 Orders Export Class
```php
getSalesReport($this->user, $this->filters);
    }

    public function headings(): array
    {
        return [
            'Order Number',
            'Date',
            'Event',
            'Category',
            'Customer Name',
            'Customer Email',
            'Customer Phone',
            'Items',
            'Subtotal',
            'Total',
            'Status',
            'Payment ID',
        ];
    }

    public function map($order): array
    {
        $items = $order->items->map(fn($item) => 
            "{$item->item_name} x{$item->quantity}"
        )->join(', ');

        return [
            $order->order_number,
            $order->paid_at?->format('Y-m-d H:i'),
            $order->event->name,
            $order->event->category?->name ?? 'N/A',
            $order->customer_name,
            $order->customer_email,
            $order->customer_phone ?? 'N/A',
            $items,
            '$' . number_format($order->subtotal, 2),
            '$' . number_format($order->total, 2),
            ucfirst($order->status),
            $order->stripe_payment_intent_id ?? 'N/A',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
```

---

## 12. Authorization & Policies

### 12.1 Event Policy
```php
<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    /**
     * Determine if user can view any events
     */
    public function viewAny(User $user): bool
    {
        return true; // All authenticated users can list events
    }

    /**
     * Determine if user can view the event
     */
    public function view(User $user, Event $event): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if (!$event->category_id) {
            return false;
        }

        return $user->hasAccessToCategory($event->category_id);
    }

    /**
     * Determine if user can create events
     */
    public function create(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        // User must have edit permission on at least one category
        return $user->categories()
            ->wherePivotIn('permission', ['edit', 'manage'])
            ->exists();
    }

    /**
     * Determine if user can update the event
     */
    public function update(User $user, Event $event): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if (!$event->category_id) {
            return false;
        }

        return $user->canEditCategory($event->category_id);
    }

    /**
     * Determine if user can delete the event
     */
    public function delete(User $user, Event $event): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if (!$event->category_id) {
            return false;
        }

        return $user->canManageCategory($event->category_id);
    }
}
```

### 12.2 Category Policy
```php
isSuperAdmin()) {
            return true;
        }

        return $user->hasAccessToCategory($category->id);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, Category $category): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->isSuperAdmin();
    }
}
```

### 12.3 Register Policies

#### app/Providers/AuthServiceProvider.php
```php
<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Event;
use App\Policies\CategoryPolicy;
use App\Policies\EventPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Event::class => EventPolicy::class,
        Category::class => CategoryPolicy::class,
    ];

    public function boot(): void
    {
        // Gate for user management (super_admin only)
        Gate::define('manage-users', function ($user) {
            return $user->isSuperAdmin();
        });
    }
}
```

### 12.4 Middleware

#### EnsureUserIsActive.php
```php
user() && !$request->user()->is_active) {
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'error' => 'Your account has been deactivated.',
            ], 403);
        }

        return $next($request);
    }
}
```

Register in `bootstrap/app.php`:
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->api(append: [
        \App\Http\Middleware\EnsureUserIsActive::class,
    ]);
})
```

---

## 13. File Structure
```
app/
├── Exports/
│   ├── OrdersExport.php
│   └── SalesExport.php
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       ├── AuthController.php
│   │       ├── GoogleAuthController.php
│   │       ├── CategoryController.php
│   │       ├── EventController.php
│   │       ├── EventItemController.php
│   │       ├── PublicEventController.php
│   │       ├── CheckoutController.php
│   │       ├── WebhookController.php
│   │       ├── UserController.php
│   │       ├── DashboardController.php
│   │       └── ReportController.php
│   ├── Middleware/
│   │   └── EnsureUserIsActive.php
│   ├── Requests/
│   │   ├── Auth/
│   │   │   └── LoginRequest.php
│   │   ├── CreateCheckoutRequest.php
│   │   ├── StoreEventRequest.php
│   │   ├── UpdateEventRequest.php
│   │   ├── StoreCategoryRequest.php
│   │   └── StoreUserRequest.php
│   └── Resources/
│       ├── CategoryResource.php
│       ├── EventResource.php
│       ├── EventItemResource.php
│       ├── OrderResource.php
│       └── UserResource.php
├── Mail/
│   └── OrderConfirmation.php
├── Models/
│   ├── ActivityLog.php
│   ├── Category.php
│   ├── Event.php
│   ├── EventItem.php
│   ├── Order.php
│   ├── OrderItem.php
│   └── User.php
├── Policies/
│   ├── CategoryPolicy.php
│   └── EventPolicy.php
├── Providers/
│   ├── AppServiceProvider.php
│   ├── AuthServiceProvider.php
│   └── FortifyServiceProvider.php
└── Services/
    ├── ImageService.php
    ├── ReportService.php
    └── StripeService.php

config/
├── fortify.php
├── sanctum.php
├── services.php
└── filesystems.php

database/
├── migrations/
│   ├── 0001_01_01_000000_create_users_table.php
│   ├── 0001_01_01_000001_create_cache_table.php
│   ├── 0001_01_01_000002_create_jobs_table.php
│   ├── 2024_01_01_000001_update_users_table.php
│   ├── 2024_01_01_000002_create_categories_table.php
│   ├── 2024_01_01_000003_create_category_user_table.php
│   ├── 2024_01_01_000004_create_events_table.php
│   ├── 2024_01_01_000005_create_event_items_table.php
│   ├── 2024_01_01_000006_create_orders_table.php
│   ├── 2024_01_01_000007_create_order_items_table.php
│   └── 2024_01_01_000008_create_activity_logs_table.php
└── seeders/
    ├── DatabaseSeeder.php
    ├── AdminUserSeeder.php
    └── CategorySeeder.php

docker/
├── supervisord.conf
└── php.ini

resources/
└── views/
    └── emails/
        └── orders/
            └── confirmation.blade.php

routes/
├── api.php
├── console.php
└── web.php

tests/
├── Feature/
│   ├── Auth/
│   │   └── LoginTest.php
│   ├── Event/
│   │   └── EventCrudTest.php
│   └── Checkout/
│       └── CheckoutTest.php
└── Unit/
    ├── EventTest.php
    └── OrderTest.php

.env.example
docker-compose.yml
Dockerfile
```

---

## 14. Environment Variables

### .env.example
```env
APP_NAME=EventHub
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_TIMEZONE=UTC
APP_URL=https://api.eventhub.com
FRONTEND_URL=https://eventhub.com

LOG_CHANNEL=stack
LOG_LEVEL=info

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=eventhub
DB_USERNAME=eventhub
DB_PASSWORD=secret

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=s3
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
CACHE_STORE=redis

REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=mailgun
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@eventhub.com"
MAIL_FROM_NAME="${APP_NAME}"
MAILGUN_DOMAIN=
MAILGUN_SECRET=

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=eventhub-uploads
AWS_URL=
AWS_ENDPOINT=
AWS_USE_PATH_STYLE_ENDPOINT=false

STRIPE_KEY=pk_test_xxx
STRIPE_SECRET=sk_test_xxx
STRIPE_WEBHOOK_SECRET=whsec_xxx

SANCTUM_STATEFUL_DOMAINS=localhost:3000,eventhub.com

GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=${APP_URL}/api/auth/google/callback
```

---

## 15. Testing Requirements

### 15.1 Test Structure
```
tests/
├── Feature/
│   ├── Auth/
│   │   ├── LoginTest.php
│   │   ├── LogoutTest.php
│   │   └── PasswordResetTest.php
│   ├── Category/
│   │   └── CategoryCrudTest.php
│   ├── Event/
│   │   ├── EventCrudTest.php
│   │   ├── EventItemTest.php
│   │   └── EventPublishTest.php
│   ├── Checkout/
│   │   ├── CheckoutSessionTest.php
│   │   └── WebhookTest.php
│   ├── User/
│   │   └── UserManagementTest.php
│   └── Report/
│       └── ReportTest.php
└── Unit/
    ├── Models/
    │   ├── EventTest.php
    │   ├── OrderTest.php
    │   └── UserTest.php
    └── Services/
        ├── ReportServiceTest.php
        └── StripeServiceTest.php
```

### 15.2 Critical Test Cases

**Authentication:**
- [ ] Login with valid credentials returns token
- [ ] Login with invalid credentials returns 401
- [ ] Login with inactive account returns 403
- [ ] Logout revokes token
- [ ] Password reset sends email
- [ ] Password reset with valid token works

**Events:**
- [ ] Super admin can create event in any category
- [ ] Admin can create event only in assigned categories
- [ ] Viewer cannot create events
- [ ] Event slug is auto-generated and unique
- [ ] Publishing event creates Stripe product/price
- [ ] Soft delete preserves order history

**Checkout:**
- [ ] Cannot checkout for draft event
- [ ] Cannot checkout past registration deadline
- [ ] Cannot checkout when registration_open is false
- [ ] Cannot checkout when sold out
- [ ] Successful checkout creates pending order
- [ ] Webhook marks order as completed
- [ ] Webhook increments tickets_sold

**Permissions:**
- [ ] Super admin sees all events
- [ ] Admin sees only events in assigned categories
- [ ] User without category access gets 403

---

## 16. Deployment Checklist

### Pre-Deployment
- [ ] All environment variables configured
- [ ] Stripe webhook endpoint registered
- [ ] S3 bucket created with proper CORS
- [ ] Mailgun domain verified
- [ ] Google OAuth credentials configured
- [ ] Database migrations tested
- [ ] Admin user seeder ready

### Deployment Steps
1. Build Docker image: `docker build -t eventhub/api .`
2. Push to registry: `docker push eventhub/api`
3. Deploy containers: `docker-compose up -d`
4. Run migrations: Automatic via start.sh
5. Seed initial data: `docker exec app php artisan db:seed`
6. Test endpoints: `curl https://api.eventhub.com/api/public/events`

### Post-Deployment
- [ ] Verify Stripe webhook connectivity
- [ ] Test complete checkout flow
- [ ] Verify email delivery
- [ ] Check S3 upload functionality
- [ ] Verify Google OAuth flow
- [ ] Monitor logs for errors

### Stripe Webhook Registration
Register these events in Stripe Dashboard:
- `checkout.session.completed`
- `payment_intent.payment_failed`
- `charge.refunded`

Webhook URL: `https://api.eventhub.com/api/webhooks/stripe`

---

## Summary

This API provides:

| Feature | Status |
|---------|--------|
| Email/Password Auth | ✅ |
| Google OAuth | ✅ |
| Category-based Permissions | ✅ |
| Event CRUD | ✅ |
| Event Extra Items | ✅ |
| Registration Deadlines | ✅ |
| Manual Registration Toggle | ✅ |
| Stripe Checkout | ✅ |
| Webhook Handling | ✅ |
| Order Confirmation Email | ✅ |
| S3 Image Uploads | ✅ |
| Excel Reports | ✅ |
| Role-based Access Control | ✅ |
| Docker Deployment | ✅ |