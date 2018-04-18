# Upgrading code

Once you have finished [namespacing your code](add-namespace.md), you can run the below code to rename all references.

```bash
upgrade-code upgrade <path> [--root-dir=<root>] [--write] [--rule] [-vvv]
```

E.g.

```bash
upgrade-code upgrade ./mysite/code
```

This will look at all class maps, and rename any references to renamed classes to the correct value.

In addition all .yml config files will have strings re-written. In order to upgrade only PHP files
you can use the `--rule=code`. If you have already upgraded your code, you can target only
config files with `--rule=config`.

## Excluding strings from upgrade

When upgrading code that contains strings, the upgrader will need to make assumptions about whether
a string refers to a class name or not, and will determine if that is a candidate for replacement.

If you have a code block with strings that do not represent class names, and thus should be excluded
from rewriting (even if they match the names of classes being rewritten) then you you can add a
docblock with the `@skipUpgrade` tag, and the upgrader will not alter any of this code.

E.g.

```PHP
/** @skipUpgrade */
return Injector::inst()->get('MyService');
```


In the above example, MyService will not be modified even if it would otherwise be renamed.

This doc block can be applied either immediately before the statement with the string, or
before a block of code (such as a method, loop, or conditional).

Note that `@skipUpgrade` does not prevent upgrade of class literals, and only affects strings,
as these are not ambiguous, and the upgrader can safely update these references.

## Upgrading Database references to now namespaced DataObject subclasses

If any DataObject subclasses have been namespaced, we will need to specify them in a config file ie. legacy.yml. This tells SilverStripe to remap these class names when dev/build is run.

```YML
---
Name: mymodulelegacy
---
SilverStripe\ORM\DatabaseAdmin:
  classname_value_remapping:
    MyModelClass: 'Me\MyProject\Model\MyModelClass'  
```

## Upgrading localisations

You can also upgrade all localisation strings in the below files:

 - keys in lang/*.yml
 - _t() method keys in all .php files
 - <%t and <% _t() method keys in all .ss files

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
