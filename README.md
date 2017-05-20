# monolog-zenddb
ZendDb driven MysqlHandler for Monolog. This package is derived from wazaari/monolog-mysql but instead of a PDO instance works with a ZendDb Adapter instance

# getting started
Add a `repository` key to your composer.json:

```json
"repositories": [
    {
        "type": "git",
        "url": "https://github.com/vardumper/monolog-zenddb"
    }
],
```

Add the repository to the require section of your composer.json
```json
"require": {
        ...
        "vardumper/monolog-zenddb": "dev-master",
        ...
```

Run a regular `composer update`. Done.

# Usage
```php
$monolog = new Logger($channel);
$dbhandler = new ZendDbHandler($adapter, $logtable, $logfields, true);
$monolog->pushHandler($dbhandler);
```