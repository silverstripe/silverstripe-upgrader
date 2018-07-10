# Releasing a new verison of the upgrader

This document is aimed for internal consumption. To release a new version of the upgrader follow these steps.

1. On your local system, clone the upgrader codebase. You need to work of the `master` branch from this point.
2. Increment the version number in `src/upgrade-code`, commit your changes and push them back up to GitHub.
3. Call `php build.php` on the command line. This will build the upgrader as a PHAR file. 
4. Move `upgrade-code.phar` to a temporary folder.
5. Switch to the `gh-pages` branch. e.g.: `git checkout gh-pages`
6. Overwrite the existing `upgrade-code.phar` with the one you built in step 3.
7. Commit your changes to the `gh-pages` branch and push them up to GitHub. 
8. Create a new release on GitHub and attach `upgrade-code.phar` to it.
