# Upgrading project files / bootstrapping

When migrating from prior versions certain project resources (e.g. .htaccess / index.php)
could be outdated and leave the site in an uninstallable condition.

You can run the below command on a project to run a set of tasks designed to automatically
resolve these issues:

```bash
upgrade-code doctor [--root-dir=<root>]
```

Tasks can be specified in `.upgrade.yml` with the following syntax:

```YML
doctorTasks:
  SilverStripe\Dev\CleanupInstall: src/Dev/CleanupInstall.php
```

The given task must have an `__invoke()` method. This will be passed the following args:

 - InputInterface $input
 - OutputInterface $output
 - string $basePath Path to project root

Note: It's advisable to only run this if your site is non-responsive, as these may override
user-made customisations to `.htaccess` or other project files.
