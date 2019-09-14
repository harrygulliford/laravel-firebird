# Firebird for Laravel

[![Latest Stable Version](https://poser.pugx.org/harrygulliford/laravel-firebird/v/stable)](https://packagist.org/packages/harrygulliford/laravel-firebird)
[![Total Downloads](https://poser.pugx.org/harrygulliford/laravel-firebird/downloads)](https://packagist.org/packages/harrygulliford/laravel-firebird)
[![License](https://poser.pugx.org/harrygulliford/laravel-firebird/license)](https://packagist.org/packages/harrygulliford/laravel-firebird)

This package adds support for the Firebird PDO driver in Laravel applications. Support for Laravel 5.5+ with PHP 7.1+ and Firebird 2.5

You can install the package via composer:

```json
composer require harrygulliford/laravel-firebird
```
The package will automatically register itself.

Declare your connection in the database config, using 'firebird' as the
driver:
```php
'firebird' => [
    'driver'   => 'firebird',
    'host'     => env('DB_HOST', 'localhost'),
    'database' => env('DB_DATABASE','/path_to/database.fdb'),
    'username' => env('DB_USERNAME', 'sysdba'),
    'password' => env('DB_PASSWORD', 'masterkey'),
    'charset'  => env('DB_CHARSET', 'UTF8'),
],
```

This package was originally forked from [acquestvanzuydam/laravel-firebird](https://github.com/jacquestvanzuydam/laravel-firebird) with enhancements from [sim1984/laravel-firebird](https://github.com/sim1984/laravel-firebird).

Licensed under the [MIT](https://choosealicense.com/licenses/mit/) licence.