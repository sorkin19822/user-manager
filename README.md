# user-manager

User management demo app built with Bootstrap, jQuery, Ajax, vanilla PHP MVC, and MySQL.

## Features

- Single-page user manager.
- User table loaded without page reload.
- Add, edit, delete, and bulk actions through Ajax.
- Bootstrap table, buttons, selects, icons, alerts, and modals.
- One Bootstrap modal reused for add and edit.
- Bootstrap confirm/warning modals, no native `alert()` or `confirm()`.
- Backend validation for required fields and allowed values.
- JSON API responses with a consistent `{status, error, ...payload}` shape.

## Stack

- PHP 8.5 with Apache
- MySQL 8
- Bootstrap 5
- Bootstrap Icons
- jQuery
- Docker Compose

## Run Locally

Create environment file:

```bash
cp .env.example .env
```

Start containers:

```bash
docker compose up -d
```

Open the app:

```text
http://localhost/
```

phpMyAdmin:

```text
http://localhost:8080
```

The MySQL schema and seed data are loaded from [sql/init.sql](sql/init.sql) when the database volume is created for the first time.

If the database volume already exists and the `users` table is missing, apply the seed manually:

```bash
docker compose exec -T mysql mysql -u root -pchange_me_root user_manager -e "source /docker-entrypoint-initdb.d/init.sql"
```

## Project Structure

```text
app/
  Config/config.php          Environment config and constants
  Controllers/UserController.php
  Core/App.php               Attribute-based front router
  Core/Controller.php        Base controller helpers
  Core/Database.php          PDO wrapper
  Core/Route.php             #[Route(path, methods)] attribute
  Models/User.php            User persistence and API formatting
  Views/layout.php
  Views/User/index.php       Bootstrap UI
public/
  index.php                  Front controller
  .htaccess                  Apache rewrite to index.php?url=...
  assets/css/style.css
  assets/js/user-manager.js  jQuery Ajax UI logic
sql/init.sql                 Database schema and seed users
```

## Routing

Apache serves [public](public) as the document root. Non-file requests are rewritten by [public/.htaccess](public/.htaccess) to:

```text
public/index.php?url=...
```

[app/Core/App.php](app/Core/App.php) scans controller methods for `#[Route]` attributes and dispatches matching requests.

HTTP methods are enforced. A known route called with the wrong method returns:

```text
405 Method Not Allowed
Allow: ...
```

`HEAD` is allowed automatically for `GET` routes.

## Pages

| Method | Path     | Description |
|--------|----------|-------------|
| GET    | `/`      | User manager UI |
| GET    | `/users` | User manager UI |

## API

| Method | Path                 | Description |
|--------|----------------------|-------------|
| GET    | `/users/list`        | List users |
| GET    | `/users/get/{id}`    | Get one user |
| POST   | `/users/create`      | Create user |
| POST   | `/users/update/{id}` | Update user |
| POST   | `/users/delete/{id}` | Delete user |
| POST   | `/users/bulk`        | Bulk action |

Bulk actions:

```text
set_active
set_inactive
delete
```

## Response Format

Errors:

```json
{
  "status": false,
  "error": {
    "code": 100,
    "message": "not found user"
  }
}
```

Create:

```json
{
  "status": true,
  "error": null,
  "id": 1
}
```

Single user:

```json
{
  "status": true,
  "error": null,
  "user": {
    "id": 1,
    "name_first": "Test1",
    "name_last": "Test2",
    "role": "user",
    "status": true
  }
}
```

User `status` is returned as boolean:

```text
true  = active
false = inactive
```

The database stores status as `active` / `inactive`.

## Validation

Backend validation is required and implemented for:

- positive integer ids;
- non-empty `name_first`;
- non-empty `name_last`;
- role must be `admin` or `user`;
- status must resolve to active or inactive;
- bulk ids must be a non-empty array of positive integers;
- bulk action must be `set_active`, `set_inactive`, or `delete`.

## Manual QA

Start from a clean local state:

```bash
docker compose up -d
```

Open:

```text
http://localhost/
```

Check:

- table loads users without page reload;
- master checkbox selects all rows;
- unchecking one row unchecks the master checkbox;
- Add opens the user modal and creates a row;
- Edit opens the same modal with user data;
- Delete opens a Bootstrap confirmation modal;
- OK with action and no users opens the no-users modal;
- OK with users and no action opens the no-action modal;
- bulk Set active and Set not active update selected users;
- bulk Delete asks for confirmation;
- no native browser `alert()` or `confirm()` appears.

## Useful Commands

PHP syntax checks:

```bash
docker compose exec -T php php -l /var/www/html/app/Core/App.php
docker compose exec -T php php -l /var/www/html/app/Controllers/UserController.php
docker compose exec -T php php -l /var/www/html/app/Models/User.php
```

API smoke checks:

```bash
docker compose exec -T php curl -s http://localhost/users/list
docker compose exec -T php curl -s http://localhost/users/get/1
docker compose exec -T php curl -s -X POST http://localhost/users/create
```

Stop containers:

```bash
docker compose down
```

Stop containers and remove database data:

```bash
docker compose down -v
```

