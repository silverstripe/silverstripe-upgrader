# Upgrader

[![Build Status](https://travis-ci.org/silverstripe/silverstripe-upgrader.svg?branch=master)](https://travis-ci.org/silverstripe/silverstripe-upgrader)
[![SilverStripe supported module](https://img.shields.io/badge/silverstripe-supported-0071C4.svg)](https://www.silverstripe.org/software/addons/silverstripe-commercially-supported-module-list/)

Upgrader is a framework for automating the upgrade of code to handle API changes in dependent libraries.
See the [4.x upgrading guide](https://docs.silverstripe.org/en/4/upgrading/) for step-by-step instructions.

Developed by @sminnee and @tractorcow with inspiration and encouragement from @camspiers

## Install

To install globally run:

`composer global require silverstripe/upgrader`

Make sure your `$HOME/.composer/vendor/bin` directory is in your PATH (or the equivalent for your OS e.g. `C:\Users\<COMPUTER NAME>\AppData\Roaming\Composer\vendor\bin` on Windows).

`echo 'export PATH=$PATH:~/.composer/vendor/bin/'  >> ~/.bash_profile`

Then you can run this script with `upgrade-code <command>` in your project root. If not running in the root,
use --root-dir=/path.

## Available commands

### Overview

The following commands are available:
* [`all`](#all): Aggregate all the commands required to upgrade a SilverStripe project.
* [`add-namespace`](#add): Add a namespace to a file.
* [`recompose`](#recompose): Upgrade a composer file to use the latest version of SilverStripe.
* [`doctor`](#doctor): Run all cleanup tasks configured for this project
* [`environment`](#environment): Migrate settings from `_ss_environment.php` to .env
* [`inspect`](#inspect): Runs additional post-upgrade inspections, warnings, and rewrites to tidy up loose ends
* [`reorganise`](#reorganise): Reorganise project folders from the SS3 `mysite` convention to the SS4 `app` convention
* [`upgrade`](#upgrade): Upgrade a set of code files to work with a newer version of a library.
* [`webroot`](#webroot): Update a SilverStripe project to use the `public` webroot.

### `all`

Run all commands in the recommended order with sensible default values.

```bash
upgrade-code all \
    [--root-dir=<root>] \
    [--composer-path=composer] \
    [--strict] \
    [--recipe-core-constraint=RECIPE-CORE-CONSTRAINT] \ 
    [--namespace] \
    [--skip-reorganise] \
    [--skip-webroot] \
    [--skip-add-namespace] \
    [--psr4]
```

Example:

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
* `--namespace` and `--psr4` are relayed to the `add-namespace` command.
* `--strict` and `--recipe-core-constraint` are relayed to the `recompose` command.

### `add-namespace`

You can run the below to add namespace to any class

```bash
upgrade-code add-namespace <namespace> <filepath> [--root-dir=<dir>] [--write] [--recursive] [--psr4] [-vvv]
```

Example:

```bash
upgrade-code add-namespace "My\Namespace" ./mysite/code/SomeCode.php --write -vvv
```

* Make sure you run this in your project root, or set the root with --root-dir.
* Note that it's important to either quote or double-escape slashes in the namespace.
* If you want to just do a dry-run, skip the `--write` param.
* If you have multiple namespace levels you can add the `--recursive` or `-r` param to also update those levels.
* If your filepath follows PSR-4 you can add the `--psr4` or `-p` param with the recursive param to auto-complete 
namespaces in child directories.

This will namespace the class file SomeCode.php, and add the mapping to the `./.upgrader.yml` file in your project.

Once you've added namespaces, you will need to run the `upgrade` task below to migrate code
that referenced the un-namespaced versions of these classes.

### `recompose`

You can use this command to upgrade your `composer.json` dependencies from SivlerStripe 3 to Silverstripe 4.

```bash
upgrade-code recompose [--root-dir=<dir>] [--write] [--strict]  [-vvv] [--recipe-core-constraint=*] [--composer-path=composer]
```

Example:

```bash
upgrade-code recompose --root-dir=/var/www/SS_project --write --recipe-core-constraint="4.1"
```

* You may end up with broken dependencies after running this command. You'll have to resolve those broken issues
manually.
* You can specify which version of SilverStripe you want to upgrade to via the `--recipe-core-constraint` option.
  If left blank, you'll be upgraded to the latest stable version.
* This script relies on composer to fetch the latest dependencies. If `composer` is in your path and is called
`composer` or `composer.phar`, you don't need to do anything. Otherwise you'll have to specify the `--composer-path`
option.
* If you specify the `--strict` option, constraints on your depdencies will be a bit more rigid.
* If you want to just do a dry-run, skip the `--write` params. You will be given a change to save your changes at the
end.

### `doctor`

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

### `environment`

You can use this command to migrate an SilverStripe 3 `_ss_environment.php` file to the `.env` format used by
SilverStripe 4.

```bash
upgrade-code environment [--root-dir=<dir>] [--write] [-vvv]
```

Example:

```bash
upgrade-code environment --root-dir=/var/www/SS_project --write -vvv
```

* The command doesn't assume your `_ss_environment.php` file is in your root folder. Like SilverStripe 3, it will
recursively check the parent folder until it finds an `_ss_environment.php` or an unreadable folder.
* If your `_ss_environment.php` file contains unusual logic (conditional statements or loops), you will get a warning.
`upgrade-code` will still try to convert the file, but you should double-check the output.
* If you want to just do a dry-run, skip the `--write` params.

### `inspect`

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

### `reorganise`

You can use this command to reorganise your folder structure to conform to the new structure introduced with SilverStripe 4.1.
Your `mysite` folder will be renamed to `app` and your `code` folder will be rename to `src`.

`upgrade-code reorganise [--root-dir=<dir>] [--write] [--recursive] [-vvv]`

Example:

`upgrade-code reorganise --root-dir=/var/www/SS_project --write -vvv`

* If you want to just do a dry-run, skip the `--write` params.
* The command will attempt to find any occurrence of _mysite_ in your codebase and show those as warnings.


### `upgrade`

Once you have finished [namespacing your code](#add-namespace), you can run the below code to rename all references.

```bash
upgrade-code upgrade <path> [--root-dir=<root>] [--write] [--rule] [-vvv]
```

Example

```bash
upgrade-code upgrade ./mysite/code
```

This will look at all class maps, and rename any references to renamed classes to the correct value.

In addition all .yml config files will have strings re-written. In order to upgrade only PHP files
you can use the `--rule=code`. If you have already upgraded your code, you can target only
config files with `--rule=config`.

#### Excluding strings from upgrade

When upgrading code that contains strings, the upgrader will need to make assumptions about whether
a string refers to a class name or not, and will determine if that is a candidate for replacement.

If you have a code block with strings that do not represent class names, and thus should be excluded
from rewriting (even if they match the names of classes being rewritten) then you you can add a
docblock with the `@skipUpgrade` tag, and the upgrader will not alter any of this code.

Example:

```PHP
/** @skipUpgrade */
return Injector::inst()->get('MyService');
```

In the above example, `MyService` will not be modified even if it would otherwise be renamed.

This doc block can be applied either immediately before the statement with the string, or
before a block of code (such as a method, loop, or conditional).

Note that `@skipUpgrade` does not prevent upgrade of class literals, and only affects strings,
as these are not ambiguous, and the upgrader can safely update these references.

#### Upgrading Database references to now namespaced DataObject subclasses

If any DataObject subclasses have been namespaced, we will need to specify them in a config file ie. legacy.yml. This tells SilverStripe to remap these class names when dev/build is run.

```YML
---
Name: mymodulelegacy
---
SilverStripe\ORM\DatabaseAdmin:
  classname_value_remapping:
    MyModelClass: 'Me\MyProject\Model\MyModelClass'  
```

#### Upgrading localisations

You can also upgrade all localisation strings in the below files:

 - keys in `lang/*.yml`
 - `_t()` method keys in all `*.php` files
 - `<%t` and `<% _t()` method keys in all `*.ss` files

You can run the upgrader on these keys with the below command:

```bash
upgrade-code upgrade <path> --rule=lang
```

Since this upgrade is normally only done on projects that provide their own strings,
this rule is not included by default when running a normal upgrade.

## .upgrade.yml spec

The .upgrade.yml file will follow the below spec:

```yaml
# Upgrade these classes
mappings:
  OldClass: My\New\Class
  SS_MyClass: NewClass
# File extensions to look at
fileExtensions:
  - php
# Don't rewrite these `private static config` settings
skipConfigs:
  - db
  - db_for_versions_table
# Don't rewrite these keys in YML
skipYML:
  - MySQLDatabase
  - Filesystem
# Namespaces to add (note: It's recommended to specify these on the CLI instead of via config file)
add-namespace:
  namespace: The\Namespace
  path: src/
# List of tasks to run when running `upgrade-code doctor`
doctorTasks:
  SilverStripe\Dev\CleanupInstall: src/Dev/CleanupInstall.php
warnings:
  classes:
    MyClass:
      message: 'MyClass has been removed'
      url: 'http://my-domain/upgrade-instructions'
  methods:
    'MyClass->myInstanceMethod()':
      message: 'Use otherMethod() instead'
      replacement: 'otherMethod'
    'MyClass::myStaticMethod()':
      message: 'Use otherMethod instead'
      replacement: 'otherMethod'
    'obsoleteMethod()':
      message: 'obsoleteMethod is removed'
  props:
    'MyClass->myInstanceProp'
      message: 'Use otherProp instead'
      replacement: 'otherProp'
    'MyClass::myStaticProp'
      message: 'Use otherProp instead'
      replacement: 'otherProp'
    'obsoleteProp':
      method: 'obsoleteProp is removed'
  functions:
    'myFunction()':
      message: 'Use otherFunction() instead'
      replacement: 'otherFunction'
  constants:
    'MY_CONSTANT':
      message: 'Use OTHER_CONSTANT instead'
      replacement: 'OTHER_CONSTANT'
    'MyClass::MY_CONSTANT':
      message: 'Use OTHER_CONSTANT instead'
      replacement: 'OTHER_CONSTANT'
```

### `webroot`

Configure your project to use the `public` web root structure introduced with SilverStripe 4.1 ([details]((https://docs.silverstripe.org/en/4/changelogs/4.1.0/#upgrade-public-folder-optional)).

```bash
upgrade-code webroot [--root-dir=<root>] [--write] [--composer-path=composer] [-vvv]
```

Example:

```bash
upgrade-code webroot /var/www/ss_project
```

* Your project must be using `silverstripe/recipe-core` 4.1 or greater to use this command. Otherwise you'll get a 
warning.
* If you've customised your server configuration files (`.htaccess` and/or `web.config`), you'll have to reconcile 
those manually with the generic ones provided by `silverstripe/recipe-core`.
* After running this command, you need to update your virtual host configuration to point to the newly created `public`
folder and you need to rewrite any hardcoded paths. 
