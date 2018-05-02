# Namespacing classes

You can run the below to add namespace to any class

```bash
upgrade-code add-namespace <namespace> <filepath> [--root-dir=<dir>] [--write] [--recursive] [-r] [-vvv]
```

E.g.

```
upgrade-code add-namespace "My\Namespace" ./mysite/code/SomeCode.php --write -vvv
```

* Make sure you run this in your project root, or set the root with --root-dir.
* Note that it's important to either quote or double-escape slashes in the namespace.
* If you want to just do a dry-run, skip the `--write` and `--upgrade-spec` params.
* If you have multiple namespace levels you can add the `--recursive` or `-r` param to also update those levels.

This will namespace the class file SomeCode.php, and add the mapping to the `./.upgrader.yml` file in your project.

Once you've added namespaces, you will need to run the `upgrade` task below to migrate code
that referenced the un-namespaced versions of these classes.
