# Wordpress Domain Updater

For Wordpress multi-site installations.

This script attempts to update the hard-coded domain (or domain/path combo) of a Wordpress installation.  Note that its main function is to perform a find/replace within the Wordpress database.

## Usage

NOTE: Always back up your entire wordpress installation before you try this script.  Also, if you're changing the domain of your site, you're likely not live anyway, but it should be said anyhow: *this script is not meant to be used on a live production site!!*

```bash
git clone git@github.com:castiron/Wordpress-Domain-Updater.git;
cp Wordpress-Domain-Updater/wp-domain-update.php path/to/your/wordpress/webroot/;
cd path/to/your/wordpress/webroot/;
php ./wp-domain-update.php
```

Follow the instructions, then: `rm ./wp-domain-update.php`
