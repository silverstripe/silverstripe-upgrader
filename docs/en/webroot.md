# Switching to the public web root

Configure your project a SivlerStripe project to use the `public` web root structure introduced with SilverStripe 4.1.

```bash
upgrade-code webroot [--root-dir=<root>] [--write] [--composer-path=composer] [-vvv]
```

E.g.

```bash
upgrade-code webroot /var/www/ss_project
```

* Your project must be using `silverstripe/recipe-core` 1.1 or greater to use this command. Otherwise you'll get a 
warning.
* If you've customised your server configuration files (`.htaccess` and/or `web.config`), you'll have to reconcile 
those manually with the generic ones provided by `silverstripe/recipe-core`.
* After running this command, you need to update your virtual host configuration to point to the newly created `public`
folder and you need to rewrite any hardcoded paths. 
