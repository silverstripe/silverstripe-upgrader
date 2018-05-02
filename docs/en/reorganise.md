# Reorganise

You can use this command to reorganise your folder structure to conform to the new structure introduced with SilverStripe 4.1.
Basically, your `mysite` folder will be renamed to `app` and your `code` folder will be rename to `src`.

`upgrade-code reorganise [--root-dir=<dir>] [--write] [--recursive] [-vvv]`

E.g.

`upgrade-code reorganise --root-dir=/var/www/SS_project --write -vvv`

* If you want to just do a dry-run, skip the `--write` params.
* The command will attempt to find any occurrence of _mysite_ in your codebase and show those as warnings.
