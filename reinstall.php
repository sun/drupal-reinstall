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
use Drupal\Core\Database\Database;

define('MAINTENANCE_MODE', 'install');

require_once __DIR__ . '/core/includes/bootstrap.inc';

// Find and prime the correct site directory like the installer.
// The site directory may be empty.
$site_path = conf_path(FALSE);

try {
  require_once DRUPAL_ROOT . '/core/includes/cache.inc';
  $settings['cache']['default'] = 'cache.backend.memory';

  $conf['lock_backend'] = 'Drupal\Core\Lock\NullLockBackend';

  drupal_bootstrap(DRUPAL_BOOTSTRAP_CONFIGURATION);

  // Purge PHP storage.
  // @see drupal_rebuild()
  PhpStorageFactory::get('service_container')->deleteAll();
  PhpStorageFactory::get('twig')->deleteAll();

  // Drop all tables.
  $connection = Database::getConnection();
  $tables = $connection->schema()->findTables('%');
  foreach ($tables as $table) {
    $connection->schema()->dropTable($table);
  }
  // Delete entire SQLite database file, if applicable.
  if ($connection->driver() == 'sqlite' && !empty($GLOBALS['databases']['default']['default']['database'])) {
    Drupal\Core\Database\Database::removeConnection('default');
    $db = DRUPAL_ROOT . '/' . $GLOBALS['databases']['default']['default']['database'];
    @chmod($db, 0777);
    @unlink($db);
  }

  // Delete all (active) configuration.
  $dir = config_get_config_directory();
  $files = glob($dir . '/*.' . Drupal\Core\Config\FileStorage::getFileExtension());
  foreach ($files as $file) {
    unlink($file);
  }

  // Delete the entire sites directory, if requested.
  if (!empty($_GET['delete'])) {
    require_once DRUPAL_ROOT . '/core/includes/common.inc';
    require_once DRUPAL_ROOT . '/core/includes/file.inc';
    // Ensure that we're not deleting the default site.
    $site_parts = explode('/', conf_path());
    if (!empty($site_parts[1]) && $site_parts[1] != 'default') {
      chmod(conf_path() . '/settings.php', 0777);
      unlink(conf_path() . '/settings.php');
      @chmod(conf_path() . '/files/.ht.sqlite', 0777);
      @unlink(conf_path() . '/files/.ht.sqlite');
      @unlink(conf_path() . '/files/.htaccess');
      file_unmanaged_delete_recursive(conf_path() . '/files');
    }
    unset($_GET['delete']);
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
