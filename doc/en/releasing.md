# Releasing a new version of the upgrader

This document is aimed for internal consumption. 

Upgrader releases are now automated with a travis build. When the tag gets built on travis, the build script will automatically run and build a PHAR executable. The PHAR executable will be attached back to the release in GitHub and publish to this repo's github pages.


To release a new version of the upgrader follow these steps:

1. On your local system, clone the upgrader codebase. You need to work of the `master` branch from this point.
2. Increment the version number in `src/upgrade-code`, commit your changes and push them back up to GitHub. This will only be used for people installing the upgrader with Composer. The PHAR executable gets its version number from an environment file built into it. 
3. In GitHub create a new release. You should provide detail about what has changed since the last release, referencing PRs ideally.
4. The release won't have a PHAR executable attached to it initially. You'll have to do wait for travis to complete its build. If that build fails for whatever reason, you can delete the release and unset the tag. Fix the issue and re-release the tag.

Note that we don't do patch releases for older minor releases. People are expected to always use the latest version.  
