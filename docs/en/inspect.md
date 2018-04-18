# Post-upgrade inspection of code

Once a project has all class names migrated, and is brought up to a "loadable" state (that is, where
all classes reference or extend real classes) then the `inspect` command can be run to perform
additional automatic code rewrites.

This step will also warn of any upgradable code issues that may prevent a succesful upgrade.

Note: This step is separate from `upgrade` because your project code is loaded into real
memory during this step in order to get the complete project context. In order to prepare for this step
your site should be updated to a basic stage, including all module upgrades and namespace changes.

You can run this command (with a necessary refresh of composer autoload files) with the below:

```bash
composer dump-autoload
upgrade-code inspect <path> [--root-dir=<root>] [--write] [-vvv]
```

This will load all classes into memory and infer the types of all objects used in each file. It will
use these inferred types to automatically update method usages.
