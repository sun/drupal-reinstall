# reinstall.php

PHP front-controller to destroy and re-install a Drupal (8) site.

## Usage

Place this script into the document root of your Drupal site; i.e., `/reinstall.php`

Request it in your browser to drop all Drupal database tables and delete all
configuration, so you can re-install from scratch.

Optional GET query parameters:

* `delete=1`  
  Deletes `settings.php` and the files directory in the site directory (but not
  the site directory itself).
* `main=1`  
  Unlocks reinstallation of of the main/default site. By default, only a
  [multi-]site may be reinstalled/deleted.

You may pass additional query parameters like `langcode` and `profile`, which
will be forwarded to `/install.php`; e.g.:  
http://drupal8.test/reinstall.php?delete=1&langcode=en&profile=testing


## Installation

### Unmanaged Download (sans git)

Simply download [reinstall.php](https://github.com/sun/drupal-reinstall/raw/master/reinstall.php) into the Document Root directory of your Drupal site.

### Linux/Mac

```sh
# Wherever you normally clone git repos...
cd ~/gitrepos
git clone https://github.com/sun/drupal-reinstall.git

# In the Document Root directory of your Drupal checkout...
cd /var/www/drupal8
ln -s ~/gitrepos/drupal-reinstall/reinstall.php
```

### Windows

```bat
:: Wherever you normally clone git repos...
cd C:\gitrepos
git clone https://github.com/sun/drupal-reinstall.git

:: In the Document Root directory of your Drupal checkout...
cd H:\htdocs\drupal8
mklink /H reinstall.php C:\gitrepos\drupal-reinstall\reinstall.php
:: yields:
Created hard link for reinstall.php <<===>> C:\gitrepos\drupal-reinstall\reinstall.php
```
