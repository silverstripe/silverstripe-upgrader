# Environment

You can use this command to migrate an SilverStripe 3 `_ss_environment.php` file to the `.env` format used by
SilverStripe 4.

```bash
upgrade-code environment [--root-dir=<dir>] [--write] [--recursive] [-vvv]
```

E.g.

```bash
upgrade-code environment --root-dir=/var/www/SS_project --write -vvv
```

* The command doesn't assume your `_ss_environment.php` file is in your root folder. Like SilverStripe 3, it will
recursively check the parent folder until it finds an `_ss_environment.php` or an unreadable folder.
* If your `_ss_environment.php` file contains unusual logic (conditional statements or loops), you will get a warning.
`upgrade-code` will still try to convert the file, but you should double-check the output.
* If you want to just do a dry-run, skip the `--write` params.
