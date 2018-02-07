This module requires to install the needed PHP extensions, otherwise it will
not work correctly. There are two ways to install them:

Composer Manager
----------------

 * Use composer_manager module - https://drupal.org/project/composer_manager.
This module handle the requirements by looking the composer.json file.
Learn more about the composer at here:
https://github.com/composer/composer/blob/master/doc/00-intro.md
After you configure the composer_manager module, go to the
composer folder where the json file is generated. The default path will be:
sites/default/files/composer and execute the composer install file e.g.:

cd [DRUPAL ROOT]/sites/default/files/composer;
composer.phar install;


Composer CLI
------------

* If you are already using `composer` to manage your project you will want to
include all the required packages in the same `vendor` directory so the autoloader
doesn't autoload multiple times and cause conflicts. To use composer directly:
  * cd to the project root and require the package:
  ```bash
  composer require "nodespark/des-connector"
  ```
  You may have to add the following lines to your `composer.json` file:
  ```json
  "minimum-stability": "dev",
  "prefer-stable": true
  ```
  As the package needs the dev version of `nodespark/des-connector`.

If the above command is not available in CLI go to:
https://github.com/composer/composer/blob/master/doc/00-intro.md
and follow the install instructions.
-------------------------------------------------------------------------------


TODO:

- add Elasticsearch installation guide
