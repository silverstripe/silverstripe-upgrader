# Environment

You can use this command to upgrade your `composer.json` dependencies from SivlerStripe 3 to Silverstripe 4.

```bash
upgrade-code recompose [--root-dir=<dir>] [--write] [--strict]  [-vvv] [--recipe-core-constraint=*] [--composer-path=composer]
```

E.g.

```bash
upgrade-code recompose --root-dir=/var/www/SS_project --write --recipe-core-constraint="1.0"
```

* You may end up with broken dependencies after running this command. You'll have to resolve those broken issues
manually.
* You can specify which version of SilverStripe 4 you want to upgrade to via the `--recipe-core-constraint` option. This
expect a version of `silverstripe/recipe-core` (e.g.: 1.1 for SiverStripe 4.1). If left blank, you'll be upgraded to
the latest stable version.
* This script relies on composer to fetch the latest dependencies. If `composer` is in your path and is called
`composer` or `composer.phar`, you don't need to do anything. Otherwise you'll have to specify the `--composer-path`
option.
* If you specify the `--strict` option, constraints on your depdencies will be a bit more rigid.
* If you want to just do a dry-run, skip the `--write` params. You will be given a change to save your changes at the
end.
