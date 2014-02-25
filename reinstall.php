<?php

/**
 * @file
 * Script to destroy and re-install Drupal 8.
 *
 * Place this script into your Drupal document root; i.e., /reinstall.php
 * and request it in your browser.
 *
 * Optional query parameters:
 * - delete=1: Deletes all files within the site directory (but not the site
 *   directory itself). Ignored and not supported for the "default" site.
 *
 * You may pass additional query parameters like 'langcode' and 'profile', which
 * will be forwarded to /install.php.
 *
 * @see install_begin_request()
 */

use Drupal\Component\PhpStorage\PhpStorageFactory;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Database\Database;
use Drupal\Core\Utility\Site;

define('MAINTENANCE_MODE', 'install');

require_once __DIR__ . '/core/vendor/autoload.php';
require_once __DIR__ . '/core/includes/bootstrap.inc';

require_once __DIR__ . '/core/includes/database.inc';

// Find and prime the correct site directory like the installer.
// The site directory may be empty.
if (!function_exists('conf_path')) {
  Site::initInstaller(__DIR__);
  $site_path = Site::getPath();
}
else {
  $site_path = conf_path(FALSE);
}

try {
  $settings['cache']['default'] = 'cache.backend.memory';

  $conf['lock_backend'] = 'Drupal\Core\Lock\NullLockBackend';

  drupal_bootstrap(DRUPAL_BOOTSTRAP_CONFIGURATION);

  // Restore native PHP error/exception handlers.
  restore_error_handler();
  restore_exception_handler();

  // Purge PHP storage.
  // @see drupal_rebuild()
  PhpStorageFactory::get('service_container')->deleteAll();
  PhpStorageFactory::get('twig')->deleteAll();

  // Drop all tables.
  $info = Database::getConnectionInfo();
  if ($info['default']['driver'] != 'sqlite') {
    $connection = Database::getConnection();
    $tables = $connection->schema()->findTables($info['default']['prefix']['default'] . '%');
    foreach ($tables as $table) {
      $connection->schema()->dropTable($table);
    }
  }
  // Delete entire SQLite database file, if applicable.
  elseif (!empty($info['default']['database'])) {
    Database::removeConnection('default');
    $sqlite_db = DRUPAL_ROOT . '/' . $info['default']['database'];
    if (file_exists($sqlite_db)) {
      chmod($sqlite_db, 0777);
      unlink($sqlite_db);
    }
  }

  // Delete all (active) configuration.
  $dir = config_get_config_directory();
  $files = glob($dir . '/*.' . FileStorage::getFileExtension());
  foreach ($files as $file) {
    unlink($file);
  }

  // Delete the entire sites directory, if requested.
  if (isset($_GET['delete'])) {
    require_once __DIR__ . '/core/includes/common.inc';
    require_once __DIR__ . '/core/includes/file.inc';
    // Ensure that we're not deleting the default site.
    $site_parts = explode('/', $site_path);
    if (!empty($site_parts[1]) && $site_parts[1] != 'default') {
      chmod($site_path . '/settings.php', 0777);
      unlink($site_path . '/settings.php');
      if (file_exists($site_path . '/files/.htaccess')) {
        chmod($site_path . '/files/.htaccess', 0777);
        unlink($site_path . '/files/.htaccess');
      }
      file_unmanaged_delete_recursive($site_path . '/files');
    }
  }
}
catch (\Exception $e) {
  echo "<pre>"; var_dump($e); echo "</pre>\n";
}

$url = 'install.php';
if (!empty($_SERVER['QUERY_STRING'])) {
  $url .= '?' . $_SERVER['QUERY_STRING'];
}
header('Location: ' . $url, TRUE, 302);

// The "Location" header sends a redirect status code to the HTTP daemon. In
// some cases this can be wrong, so we make sure none of the code below the
// drupal_goto() call gets executed upon redirection.
exit();
