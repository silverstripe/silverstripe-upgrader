# All-in-one command

Run all commands in the recommended order with sensible default values.

```bash
upgrade-code all-in-one \
    [--root-dir=<root>] \
    [--composer-path=composer] \
    [--strict] \
    [--recipe-core-constraint=RECIPE-CORE-CONSTRAINT]\ 
    [--namespace="App\\Web"] \
    [--skip-reorganise] \
    [--skip-webroot] \
    [--skip-add-namespace]
```

E.g.

```bash
upgrade-code all-in-one
```

* Unless your site is very simple, it's highly unlikely this command will be successful the first time you run it. It's 
designed to be run successively to help you find errors and debug them. If the command runs successfully, you'll get a 
summary of all warnings at the end. 
* All changes are written. This is necessary because some of the later commands expect specific steps to have been 
performed beforehand.
* It's equivalent to running the following steps in the following order:
  * recompose
  * environment
  * add-namespace
  * upgrade
  * inspect
  * reorganise
  * webroot
* the `--skip` flags allows to skip optional steps.
* `--namespace` is relayed to the `add-namespace` command.
* `--strict` and `--recipe-core-constraint` are relayed to the `recompose` command.
