# Adding The Config Provider

* In `config/config.php` add an entry for the config provider `\Dot\Log\ConfigProvider::class`
    * Make sure it is added before with the Application-Specific components, eg.:
        * `\Frontend\App\ConfigProvider.php`
        * `\Admin\App\ConfigProvider::class`
        * `\MyProject\ConfigProvider::class` etc.
* Add the logger configuration in an autoload config file, e.g. you can create `config/autoload/logger.global.php`. Follow the `Configuring the writer(s)` chapter for a simple working example.

Note: `Dot\Log\ConfigProvider` has an abstract factory `LoggerAbstractServiceFactory::class` which corresponds to the alias, not  the class name. Instead of requesting `Laminas\Log\Logger::class` from the container, use `dot-log.my_logger` (or just `my_logger` if using laminas-log).
