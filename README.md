# Upgrader

[![Build Status](https://travis-ci.org/silverstripe/silverstripe-upgrader.svg?branch=master)](https://travis-ci.org/silverstripe/silverstripe-upgrader)
[![SilverStripe supported module](https://img.shields.io/badge/silverstripe-supported-0071C4.svg)](https://www.silverstripe.org/software/addons/silverstripe-commercially-supported-module-list/)

Upgrader is a framework for automating the upgrade of code to handle API changes in dependent libraries.

Still under heavy development, in the first case it will provide a PoC of tooling to assist with SilverStripe 3 to 4 upgrades.

Developed by @sminnee and @tractorcow with inspiration and encouragement from @camspiers

## Install

To install globally run:

`composer global require silverstripe/upgrader`

Make sure your `$HOME/.composer/vendor/bin` directory is in your PATH (or the equivalent for your OS e.g. `C:\Users\<COMPUTER NAME>\AppData\Roaming\Composer\vendor\bin` on Windows).

`echo 'export PATH=$PATH:~/.composer/vendor/bin/'  >> ~/.bash_profile`

Then you can run this script with `upgrade-code <command>` in your project root. If not running in the root,
use --root-dir=/path.

## Available commands

The following commands are available:
* [`add-namespace`](docs/en/add-namespace.md)
* [`all`](docs/en/all.md)
* [`recompose`](docs/en/recompose.md)
* [`doctor`](docs/en/doctor.md)
* [`environment`](docs/en/environment.md)
* [`inspect`](docs/en/inspect.md)
* [`reorganise`](docs/en/reorganise.md)
* [`upgrade`](docs/en/upgrade.md)
* [`webroot`](docs/en/webroot.md)
