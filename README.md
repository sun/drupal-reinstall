# reinstall.php

PHP front-controller to destroy and re-install a Drupal (8) site.

## Usage

Place this script into the document root of your Drupal site; i.e.,
`/reinstall.php`

Request it in your browser to drop all Drupal database tables and delete all
configuration, so you can re-install from scratch.

Without additional parameters, `settings.php` is retained as-is, so you can
re-install with your previously existing database connection info, etc.

Optional GET query parameters:

* `delete=1`  
  Additionally deletes `settings.php` and the files directory.
* `main=1`  
  Unlocks reinstallation of of the main/default site. By default, only a
  [multi-]site may be reinstalled/deleted.

Regardless of parameters, the site directory itself (and `/sites/sites.php`) is
never deleted.  This enables the Drupal installer to discover the same site
directory again.

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

## Multi-WUT?

This script and manual refers to "multiple sites" in a couple of places.  This
chapter explains what is meant by that and why it matters.

Drupal core natively supports a _multi-site_ concept; see
[`/sites/default/default.settings.php`](http://drupalcode.org/project/drupal.git/blob/HEAD:/sites/default/default.settings.php)

Drupal's multi-site functionality is normally used when you have multiple
domains pointing to the same host and identical code base. — But what if you
don't have or want that?  Can you leverage it for quick throw-away _"scratch"_
re-installations?

Yes, you can.  Assuming the following:

1. You have Drupal in `/var/www/drupal8/`
1. You normally access it via http://drupal8.local/
1. You want to access a separate _scratch_ site via http://drupal8.local/scratch/

Here is how:

1. Edit your Apache `httpd.conf` (or corresponding virtual host `*.conf` file)
   to add an `Alias`:

    ```apache
    <VirtualHost *:80>
      ServerName drupal8.local
      ...
      Alias /scratch /var/www/drupal8
    </VirtualHost>
    ```
1. Restart Apache.
1. Create an empty `/sites/scratch` directory.
1. Copy `/sites/example.sites.php` into `/sites/sites.php`
1. Add the following line to `/sites/sites.php`:

   ```php
   $sites['drupal8.local.scratch'] = 'scratch';
   ```
1. Visit http://drupal8.local/scratch/reinstall.php

Known limitation:

1. The Apache 2.4+ `mod_alias` module supplies a `$_SERVER['CONTEXT_PREFIX']`
   variable to PHP, which is not consumed in any way by Drupal.
1. `mod_alias` is only triggered when requesting a _file_ within the aliased
   location. When only requesting a directory (e.g., `/scratch`), then the
   request does **not** run against the alias.
1. Due to the above, the _final_ redirect to `/scratch` after completing the
   Drupal installation will end up in the default site. To access the newly
   installed scratch site, you have to manually inject a "dirty" URL:

    ```diff
    -http://drupal8.local/scratch/user/1
    +http://drupal8.local/scratch/index.php/user/1
    ```

The limitation does not apply to the `reinstall.php` script itself, nor to
`install.php`, since both are interpreted as _files_ by Apache.

_Tip: Use the SQLite database driver when installing your scratch site, so the
entire site is contained in files in the `/sites/scratch` directory._

