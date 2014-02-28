<?php

/**
 * @file
 * Script to destroy and re-install Drupal 8.
 *
 * Place this script into your Drupal document root; i.e., /reinstall.php
 * and request it in your browser.
 *
 * Optional GET query parameters:
 * - delete=1: Deletes settings.php and the files directory in the site
 *   directory (but not the site directory itself).
 * - main=1: Allows to reinstall/delete the main site. By default, only a
 *   [multi-]site may be reinstalled/deleted.
 *
 * You may pass additional query parameters like 'langcode' and 'profile', which
 * will be forwarded to /install.php; e.g.:
 * http://drupal8.test/reinstall.php?delete=1&langcode=en&profile=testing
 */

use Drupal\Component\PhpStorage\PhpStorageFactory;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Database\Database;
use Drupal\Core\Site\Site;

const MAINTENANCE_MODE = 'install';

require_once __DIR__ . '/core/vendor/autoload.php';
require_once __DIR__ . '/core/includes/bootstrap.inc';

// Find and prime the correct site directory like the installer.
// The site directory may be empty.
if (!function_exists('conf_path')) {
  Site::initInstaller(__DIR__);
}
else {
  $site_path = conf_path(FALSE);
}

$settings['cache']['default'] = 'cache.backend.memory';
$conf['lock_backend'] = 'Drupal\Core\Lock\NullLockBackend';

drupal_bootstrap(DRUPAL_BOOTSTRAP_CONFIGURATION);

// Restore native PHP error/exception handlers.
restore_error_handler();
restore_exception_handler();

if (!function_exists('conf_path')) {
  $site_path = Site::getPath();
}
if (empty($_GET['main']) && ($site_path === '' || $site_path === 'sites/default')) {
  print "Destruction of site <code>'$site_path'</code> is not allowed. Use the <kbd>?main=1</kbd> query parameter to unlock this protection.";
  exit;
}

// Purge PHP storage.
// @see drupal_rebuild()
PhpStorageFactory::get('service_container')->deleteAll();
PhpStorageFactory::get('twig')->deleteAll();

// Drop all database tables.
$info = Database::getConnectionInfo();
if ($info['default']['driver'] != 'sqlite') {
  $connection = Database::getConnection();
  $tables = $connection->schema()->findTables($info['default']['prefix']['default'] . '%');
  foreach ($tables as $table) {
    $connection->schema()->dropTable($table);
  }
}
// Delete SQLite database file, if applicable.
elseif (!empty($info['default']['database'])) {
  Database::removeConnection('default');
  $sqlite_db = DRUPAL_ROOT . '/' . $info['default']['database'];
  if (file_exists($sqlite_db)) {
    chmod($sqlite_db, 0777);
    unlink($sqlite_db);
  }
}

// Delete all (active) configuration.
// glob() requires an absolute path on Windows.
// realpath() returns FALSE if the directory does not exist.
if ($dir = realpath(config_get_config_directory())) {
  $files = glob($dir . '/*.' . FileStorage::getFileExtension());
  foreach ($files as $file) {
    unlink($file);
  }
}

// Delete settings.php and the files directory in the site directory, if requested.
if (!empty($_GET['delete'])) {
  if (!function_exists('conf_path')) {
    $settings_file = Site::getPath('settings.php');
    $files_dir = Site::getPath('files');
  }
  else {
    $settings_file = $site_path . '/settings.php';
    $files_dir = $site_path . '/files';
  }
  chmod($settings_file, 0777);
  unlink($settings_file);
  if (is_dir($files_dir)) {
    // Does not need a filter, because we want to delete all contents.
    $flags = \FilesystemIterator::UNIX_PATHS;
    $flags |= \FilesystemIterator::SKIP_DOTS;
    $directory = new \RecursiveDirectoryIterator($files_dir, $flags);
    $iterator = new \RecursiveIteratorIterator($directory, \RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $file) {
      chmod($file->getPathname(), 0777);
      if ($file->isDir()) {
        rmdir($file->getPathname());
      }
      else {
        unlink($file->getPathname());
      }
    }
    chmod($files_dir, 0777);
    rmdir($files_dir);
  }
}

$url = 'install.php';
if (!empty($_GET) && $query = http_build_query(array_diff_key($_GET, array('delete' => 0, 'main' => 0)))) {
  $url .= '?' . $query;
}
header('Location: ' . $url, TRUE, 302);
