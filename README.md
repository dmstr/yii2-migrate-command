Extended Migration Command
==========================

Console Migration Command with multiple paths/aliases support

This extension was created from this [Pull Request](https://github.com/yiisoft/yii2/pull/3273) on GitHub, which became unmergeable.
Until this feature will be reimplemented into the core, you can use this extension if you need to handle multiple migration paths.

> Note! If using `dmstr/yii2-migrate-command` in an existing project, you may have to remove your *migration* table, due to a schema change.

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

```
composer require dmstr/yii2-migrate-command "*"
```

Usage
-----

Configure the command in your `main` application configuration:

```
'controllerMap'       => [
    'migrate' => [
        'class' => 'dmstr\console\controllers\MigrateController'
    ],
],
```

Once the extension is installed and configured, simply use it on your command line

```
./yii migrate
```


### Adding migrations via application configuration

Add additional migration paths via application `params`:

```
"yii.migrations"=> [
    "@dektrium/user/migrations",
],
```

### Adding migrations via extension `bootstrap()`

You can also add migrations in your module bootstrap process:

```
public function bootstrap($app)
{
    $app->params['yii.migrations'][] = '@vendorname/packagename/migrations';
}
```    

### Adding migrations via command parameter

If you want to specify an additional path directly with the command, use

```
./yii migrate --migrationLookup=@somewhere/migrations/data1,@somewhere/migrations/data2
```

> Note! Please make sure to **not use spaces** in the comma separated list.

---

Built by [dmstr](http://diemeisterei.de), Stuttgart