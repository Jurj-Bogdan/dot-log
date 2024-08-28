# Formatting Messages

When using `dot-log` or `laminas-log`, the logged value is not limited to a string. Arrays can be logged as well. For better readability, these arrays can be serialized. Laminas Log provides String, XML, JSON and FirePHP formatting.

The formatter accepts following parameters:

* name - the formatter class (it must implement `Laminas\Log\Formatter\FormatterInterface`)
* options - passed to the formatter constructor if required

The following snippet formats the message as JSON data:

    'formatter' => [
        'name' => \Laminas\Log\Formatter\Json::class,
    ],
