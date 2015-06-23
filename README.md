# CakePHP Application Skeleton

<!--
TODO(slangley): Setup
[![Build Status](https://api.travis-ci.org/cakephp/app.png)](https://travis-ci.org/cakephp/app)
-->
[![License](https://poser.pugx.org/google/appengine-php-cakephp-starter-app/license.svg)](https://packagist.org/packages/google/appengine-php-cakephp-starter-app)

A skeleton for creating Google App Engine applications with [CakePHP](http://cakephp.org) 3.0.

## Installation

1. Download [Composer](http://getcomposer.org/doc/00-intro.md) or update `composer self-update`.
2. Run `php composer.phar create-project --prefer-dist google/appengine-php-cakephp-starter-app [app_name]`.

If Composer is installed globally, run
```bash
composer create-project --prefer-dist google/appengine-php-cakephp-starter-app [app_name]
```

You should now be able to visit the path to where you installed the app and see
the setup traffic lights.

## Configuration

The composer install script will ask for the production and development database configurations, and
create the correct `app.yaml` file for the application.

If these details need updating then edit the `app.yaml` and configure the MySQL environment variables to connect
to the updated MySQL configuration relevant for your application.
