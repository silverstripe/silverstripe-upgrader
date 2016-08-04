# Upgrader

[![Build Status](https://travis-ci.org/silverstripe/silverstripe-upgrader.svg?branch=master)](https://travis-ci.org/silverstripe/silverstripe-upgrader)

Upgrader is a framework for automating the upgrade of code to handle API changes in dependent libraries.

Still under heavy development, in the first case it will provide a PoC of tooling to assist with SilverStriep 3 to 4 upgrades.

Developed by @sminnee and @tractorcow with inspiration and encouragement from @camspiers

# Install

To install globally run:

`composer global require silverstripe/upgrader`

Make sure your `~/.composer/vendor/bin` directory is in your PATH.

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

`upgrade-code upgrade <path> [--recursive] [--write] [-vvv]`

E.g.

`upgrade-code upgrade .`

This will look at all class maps, and rename any references to renamed classes to the correct value.

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
