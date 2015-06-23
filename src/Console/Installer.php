<?php
/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link      http://cakephp.org CakePHP(tm) Project
 * @since     3.0.0
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace App\Console;

use Composer\Script\Event;
use Exception;

/**
 * Provides installation hooks for when this application is installed via
 * composer. Customize this class to suit your needs.
 */
class Installer
{
    /**
     * String keys in the app.yaml.dist file to be replaced with user
     * configured values.
     */
    private static $yaml_placeholders = [
      '__APPLICATION_ID__' => 'application_id',
      '__PROD_DB_HOSTNAME__' => 'prod_db_hostname',
      '__PROD_DB_PORT__' => 'prod_db_port',
      '__PROD_DB_SOCKET__' => 'prod_db_socket',
      '__PROD_DB_USERNAME__' => 'prod_db_username',
      '__PROD_DB_PASSWORD__' => 'prod_db_password',
      '__PROD_DB_DATABASE__' => 'prod_db_database',
      '__DEV_DB_HOSTNAME__' => 'prod_db_hostname',
      '__DEV_DB_PORT__' => 'dev_db_port',
      '__DEV_DB_SOCKET__' => 'dev_db_socket',
      '__DEV_DB_USERNAME__' => 'dev_db_username',
      '__DEV_DB_PASSWORD__' => 'dev_db_password',
      '__DEV_DB_DATABASE__' => 'dev_db_database',
    ];

    /**
     * Default values for the app.yaml configuration.
     */
    private static $application_defaults = [
      'application_id' => 'my-app-id',
      'prod_db_hostname' => '',
      'prod_db_port' => '3306',
      'prod_db_socket' => '',
      'prod_db_username' => 'root',
      'prod_db_password' => '',
      'prod_db_database' => 'database-name',
      'dev_db_hostname' => 'localhost',
      'dev_db_port' => '3306',
      'dev_db_socket' => '',
      'dev_db_username' => 'root',
      'dev_db_password' => '',
      'dev_db_database' => 'database-name',
    ];

    /**
     * Question descriptions during install for app.yaml configuration items.
     */
    private static $application_config_questions = [
      'application_id' => 'App Engine Application Id',
      'prod_db_hostname' => 'Production Database Hostname',
      'prod_db_port' => 'Production Database Port Number',
      'prod_db_socket' => 'Production Database Socket Address (Format: /cloudsql/<project-id>/<instance-id>)',
      'prod_db_username' => 'Production Database User Name',
      'prod_db_password' => 'Production Database Password',
      'prod_db_database' => 'Production Database Database Name',
      'dev_db_hostname' => 'Development Database Hostname',
      'dev_db_port' => 'Development Database Port Number',
      'dev_db_socket' => 'Development Database Socket Address',
      'dev_db_username' => 'Development Database User Name',
      'dev_db_password' => 'Development Database Password',
      'dev_db_database' => 'Development Database Database Name',
    ];

    /**
     * Does some routine installation tasks so people don't have to.
     *
     * @param \Composer\Script\Event $event The composer event object.
     * @throws \Exception Exception raised by validator.
     * @return void
     */
    public static function postInstall(Event $event)
    {
        $io = $event->getIO();

        $rootDir = dirname(dirname(__DIR__));

        static::createAppConfig($rootDir, $io);
        static::createAppYaml($rootDir, $io);
        static::setSecuritySalt($rootDir, $io);

        if (class_exists('\Cake\Codeception\Console\Installer')) {
            \Cake\Codeception\Console\Installer::customizeCodeceptionBinary($event);
        }
    }

    /**
     * Create the config/app.php file if it does not exist.
     *
     * @param string $dir The application's root directory.
     * @param \Composer\IO\IOInterface $io IO interface to write to console.
     * @return void
     */
    public static function createAppConfig($dir, $io)
    {
        $appConfig = $dir . '/config/app.php';
        $defaultConfig = $dir . '/config/app.default.php';
        if (!file_exists($appConfig)) {
            copy($defaultConfig, $appConfig);
            $io->write('Created `config/app.php` file');
        }
    }

    /**
     * Set the security.salt value in the application's config file.
     *
     * @param string $dir The application's root directory.
     * @param \Composer\IO\IOInterface $io IO interface to write to console.
     * @return void
     */
    public static function setSecuritySalt($dir, $io)
    {
        $config = $dir . '/config/app.php';
        $content = file_get_contents($config);

        $newKey = hash('sha256', $dir . php_uname() . microtime(true));
        $content = str_replace('__SALT__', $newKey, $content, $count);

        if ($count == 0) {
            $io->write('No Security.salt placeholder to replace.');
            return;
        }

        $result = file_put_contents($config, $content);
        if ($result) {
            $io->write('Updated Security.salt value in config/app.php');
            return;
        }
        $io->write('Unable to update Security.salt value.');
    }

    /**
     * Create the app.yaml file if it does not already exist.
     *
     * @param string $dir The application's root directory.
     * @param \Composer\IO\IOInterface $io IO interface to write to console.
     * @return void
     */
    public static function createAppYaml($dir, $io) {
        $app_yaml = $dir . '/app.yaml';
        $default_app_yaml = $dir . '/app.yaml.dist';
        if (!file_exists($app_yaml)) {
            copy($default_app_yaml, $app_yaml);
            $io->write('Created `app.yaml` file');
            static::configureAppYaml($app_yaml, $io);
        } else {
            $io->write('`app.yaml` already exists.');
        }
    }

    /**
     * Configure the app.yaml file, asking the user for the configuration.
     *
     * @param string $file The location of the app.yaml file.
     * @param \Composer\IO\IOInterface $io IO interface to write to console.
     * @return void
     */
    private static function configureAppYaml($file, $io) {
        if ($io->isInteractive()) {
          $config = [];
          $io->write('For each database enter either Host/Port information ' .
                    'or the socket address.');
          $io->write('If both are entered, then the socket address will ' .
                    'be used.');
          $io->write('If using Google CloudSQL for the production database ' .
                     'then enter the socket address only.');
          foreach(static::$application_config_questions as $key => $question) {
              $default = static::$application_defaults[$key];
              $config[$key] = $io->ask($question . " (default: $default): ",
                                       $default);
          }
          static::writeYamlConfig($file, $io, $config);
        } else {
          static::writeYamlConfig($file, $io, static::$application_defaults);
        }
    }

    /**
     * Write the app.yaml file, using the user supplied configuration.
     *
     * @param string $file The location of the app.yaml file.
     * @param \Composer\IO\IOInterface $io IO interface to write to console.
     * @param mixed $config An array of configuration data.
     * @return void
     */

    private static function writeYamlConfig($file, $io, $config) {
        $content = file_get_contents($file);

        if ($content === false) {
            $io->writeError('Unable to load app.yaml file ' . $file);
            return;
        }

        foreach(static::$yaml_placeholders as $key => $value) {
            if (!array_key_exists($value, $config)) {
                $io->writeError('Expected configuration key ' . $value .
                                'not found - aborting.');
                return;
            }

            $content = str_replace($key, $config[$value], $content, $count);
            if ($count === 0) {
                $io->writeError('Placeholder ' . $key . ' not found to replace.');
                return;
            }
        }

        $result = file_put_contents($file, $content);
        if ($result) {
            $io->write('Updated configuration in `app.yaml`.');
            $io->write('To change any settings, edit the file ' . $file);
        } else {
            $io->writeError('Unable to update configuration in `app.yaml`.');
        }
    }
}
