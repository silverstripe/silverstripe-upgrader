# Configuring your module to work with upgrader

The upgrader is written in a generic way so any module can make use of it to simplify the upgrade process for their users.

The main thing module maintainers need to do is add a `.upgrade.yml` in the root of their module. The various command in the upgrader will load this file to understand what module specific changes they need to apply. You can look at [framework's .upgrade.yml](https://github.com/silverstripe/silverstripe-framework/blob/4/.upgrade.yml) file for specific examples.

## `mappings`

This allows you to define classes that should be replaced by other classes. This is useful when namespacing your module. This is use by the `upgrade` command.

```yml
mappings:
  ArrayList: SilverStripe\ORM\ArrayList
``` 

If you call the `add-namespace` command on your module, it will create the mappings for you.

## `warnings`
Warnings can be used to note deprecated methods, classes and properties.

If there's a one-to-one replacement available, you can also use the `replacement` key which will automatically apply the change for your users.

```yml
warnings:
  classes:
    'Maintainer\AwsomeModule\DeprecatedClass':
      message: 'Replaced with traits'
      url: 'https://docs.silverstripe.org/en/4/changelogs/4.0.0#object-replace'
  methods:
    'Maintainer\AwsomeModule\AwesomeClass::deprecatedMethod()':
      message: 'Replace with a different method'
      replacement: 'newBetterMethod'
  props:
    'Maintainer\AwsomeModule\AwesomeClass->oldProperty':
      message: 'Replace with a different property'
      replacement: 'newProperty'
```

This information is used by the `inspect` command.

## `recipeEquivalences`

This allows you to get certain packages substituted by other packages when running the `recompose` command. For example, when upgrading from SilverStripe 3 to SilverStripe 4, most users will want to use `silverstripe/recipe-cms` instead of `silverstripe/cms`. This can be helpful if your package has changed name. It can also be used to replace a module with a combination of other modules.

```yml
recipeEquivalences:
  'silverstripe/framework':
    - silverstripe/recipe-core'
```

An important point to consider is that this needs to be in the SilverStripe 3 version of your module.

## `visibilities`

You can use this to adjust the visibility of properties that might have changed. This is helpful for `private static` configuration attributes. SilverStripe 3 was more forgiving and was treating any static attribute as a configuration flag regardless of visibility.

```yml
visibilities:
  'SilverStripe\ORM\DataObject::db':
    visibility: private
```

This is used by the `inspect` command.