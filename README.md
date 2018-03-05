# Upgrader

[![Build Status](https://travis-ci.org/silverstripe/silverstripe-upgrader.svg?branch=master)](https://travis-ci.org/silverstripe/silverstripe-upgrader)

Upgrader is a framework for automating the upgrade of code to handle API changes in dependent libraries.

Still under heavy development, in the first case it will provide a PoC of tooling to assist with SilverStriep 3 to 4 upgrades.

Developed by @sminnee and @tractorcow with inspiration and encouragement from @camspiers

# Install

To install globally run:

`composer global require silverstripe/upgrader`

Make sure your `$HOME/.composer/vendor/bin` directory is in your PATH (or the equivalent for your OS e.g. `C:\Users\<COMPUTER NAME>\AppData\Roaming\Composer\vendor\bin` on Windows).

`echo 'export PATH=$PATH:~/.composer/vendor/bin/'  >> ~/.bash_profile`

Then you can run this script with `upgrade-code <command>` in your project root. If not running in the root,
use --root-dir=/path.

# Namespacing classes

You can run the below to add namespace to any class

`upgrade-code add-namespace <namespace> <filepath> [--root-dir=<dir>] [--write] [--recursive] [-vvv]`

E.g.

`upgrade-code add-namespace "My\Namespace" ./mysite/code/SomeCode.php --write -vvv`

* Make sure you run this in your project root, or set the root with --root-dir.
* Note that it's important to either quote or double-escape slashes in the namespace.
* If you want to just do a dry-run, skip the `--write` and `--upgrade-spec` params.

This will namespace the class file SomeCode.php, and add the mapping to the `./.upgrader.yml` file in your project.

Once you've added namespaces, you will need to run the `upgrade` task below to migrate code
that referenced the un-namespaced versions of these classes.

# Upgrading code

Once you have finished namespacing your code, you can run the below code to rename all references.

`upgrade-code upgrade <path> [--root-dir=<root>] [--write] [--rule] [-vvv]`

E.g.

`upgrade-code upgrade ./mysite/code`

This will look at all class maps, and rename any references to renamed classes to the correct value.

In addition all .yml config files will have strings re-written. In order to upgrade only PHP files
you can use the `--rule=code`. If you have already upgraded your code, you can target only
config files with `--rule=config`.

## Post-upgrade inspection of code

Once a project has all class names migrated, and is brought up to a "loadable" state (that is, where 
all classes reference or extend real classes) then the `inspect` command can be run to perform
additional automatic code rewrites.

This step will also warn of any upgradable code issues that may prevent a succesful upgrade.

Note: This step is separate from `upgrade` because your project code is loaded into real
memory during this step in order to get the complete project context. In order to prepare for this step
your site should be updated to a basic stage, including all module upgrades and namespace changes.

You can run this command (with a necessary refresh of composer autoload files) with the below:

```
composer dump-autoload
upgrade-code inspect <path> [--root-dir=<root>] [--write] [-vvv]
```

This will load all classes into memory and infer the types of all objects used in each file. It will
use these inferred types to automatically update method usages.

## Excluding strings from upgrade

When upgrading code that contains strings, the upgrader will need to make assumptions about whether
a string refers to a class name or not, and will determine if that is a candidate for replacement.

If you have a code block with strings that do not represent class names, and thus should be excluded
from rewriting (even if they match the names of classes being rewritten) then you you can add a
docblock with the `@skipUpgrade` tag, and the upgrader will not alter any of this code.

E.g.

    :::php
    /** @skipUpgrade */
    return Injector::inst()->get('MyService');


In the above example, MyService will not be modified even if it would otherwise be renamed.
    
This doc block can be applied either immediately before the statement with the string, or
before a block of code (such as a method, loop, or conditional).

Note that `@skipUpgrade` does not prevent upgrade of class literals, and only affects strings,
as these are not ambiguous, and the upgrader can safely update these references.

## Upgrading Database references to now namespaced DataObject subclasses

If any DataObject subclasses have been namespaced, we will need to specify them in a config file ie. legacy.yml. This tells SilverStripe to remap these class names when dev/build is run.

```
---
Name: mymodulelegacy
---
SilverStripe\ORM\DatabaseAdmin:
  classname_value_remapping:
    MyModelClass: 'Me\MyProject\Model\MyModelClass'  
```

## Upgrading project files / bootstrapping

When migrating from prior versions certain project resources (e.g. .htaccess / index.php)
could be outdated and leave the site in an uninstallable condition. 

You can run the below command on a project to run a set of tasks designed to automatically
resolve these issues:

```
upgrade-code doctor [--root-dir=<root>]
```

Tasks can be specified in `.upgrade.yml` with the following syntax:

```
doctorTasks:
  SilverStripe\Dev\CleanupInstall: src/Dev/CleanupInstall.php
```

The given task must have an `__invoke()` method. This will be passed the following args:

 - InputInterface $input
 - OutputInterface $output
 - string $basePath Path to project root

Note: It's advisable to only run this if your site is non-responsive, as these may override
user-made customisations to `.htaccess` or other project files.

## Upgrading localisations

You can also upgrade all localisation strings in the below files:

 - keys in lang/*.yml
 - _t() method keys in all .php files
 - <%t and <% _t() method keys in all .ss files
 
You can run the upgrader on these keys with the below command:

`upgrade-code upgrade <path> --rule=lang`

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
