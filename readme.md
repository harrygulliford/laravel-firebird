# Firebird for Laravel

[![Latest Stable Version](https://poser.pugx.org/harrygulliford/laravel-firebird/v/stable)](https://packagist.org/packages/harrygulliford/laravel-firebird)
[![Total Downloads](https://poser.pugx.org/harrygulliford/laravel-firebird/downloads)](https://packagist.org/packages/harrygulliford/laravel-firebird)
[![Tests](https://github.com/harrygulliford/laravel-firebird/actions/workflows/tests.yml/badge.svg)](https://github.com/harrygulliford/laravel-firebird/actions/workflows/tests.yml)
[![License](https://poser.pugx.org/harrygulliford/laravel-firebird/license)](https://packagist.org/packages/harrygulliford/laravel-firebird)

This package adds support for the Firebird PDO Database Driver in Laravel applications.

## Version Support

- **PHP:** 7.4, 8.0, 8.1, 8.2, 8.3
- **Laravel:** 8.x, 9.x, 10.x, 11.x
- **Firebird:** 2.5, 3.0, 4.0

## Installation

You can install the package via composer:

```bash
composer require harrygulliford/laravel-firebird
```

_The package will automatically register itself._

Declare the connection within your `config/database.php` file by using `firebird` as the
driver:
```php
'connections' => [

    'firebird' => [
        'driver'   => 'firebird',
        'host'     => env('DB_HOST', 'localhost'),
        'port'     => env('DB_PORT', '3050'),
        'database' => env('DB_DATABASE', '/path_to/database.fdb'),
        'username' => env('DB_USERNAME', 'sysdba'),
        'password' => env('DB_PASSWORD', 'masterkey'),
        'charset'  => env('DB_CHARSET', 'UTF8'),
        'role'     => null,
    ],

],
```

To register this package in Lumen, you'll also need to add the following line to the service providers in your `config/app.php` file:
`$app->register(\HarryGulliford\Firebird\FirebirdServiceProvider::class);`

## Limitations
This package does not intend to support database migrations and it should not be used for this use case.

## Credits
- [Harry Gulliford](https://github.com/harrygulliford)
- [Jacques van Zuydam](https://github.com/jacquestvanzuydam/laravel-firebird)
- [Simonov Denis](https://github.com/sim1984/laravel-firebird)
- [All Contributors](https://github.com/harrygulliford/laravel-firebird/graphs/contributors)

## License
Licensed under the [MIT](https://choosealicense.com/licenses/mit/) license.
