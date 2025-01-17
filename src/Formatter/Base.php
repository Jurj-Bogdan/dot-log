<?php

declare(strict_types=1);

namespace Dot\Log\Formatter;

use DateTime;
use Traversable;

use function defined;
use function get_resource_type;
use function gettype;
use function is_array;
use function is_object;
use function is_resource;
use function is_scalar;
use function iterator_to_array;
use function json_encode;
use function method_exists;
use function sprintf;

use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

class Base implements FormatterInterface
{
    /**
     * Format specifier for DateTime objects in event data (default: ISO 8601)
     *
     * @see http://php.net/manual/en/function.date.php
     */
    protected string $dateTimeFormat = self::DEFAULT_DATETIME_FORMAT;

    /**
     * @see http://php.net/manual/en/function.date.php
     */
    public function __construct(string|iterable|null $dateTimeFormat = null)
    {
        if ($dateTimeFormat instanceof Traversable) {
            $dateTimeFormat = iterator_to_array($dateTimeFormat);
        }

        if (is_array($dateTimeFormat)) {
            $dateTimeFormat = $dateTimeFormat['dateTimeFormat'] ?? null;
        }

        if (null !== $dateTimeFormat) {
            $this->dateTimeFormat = $dateTimeFormat;
        }
    }

    /**
     * Formats data to be written by the writer.
     *
     * @psalm-suppress InvalidReturnType
     * @psalm-suppress InvalidReturnStatement
     */
    public function format(iterable $event): iterable|string
    {
        foreach ($event as $key => $value) {
            // Keep extra as an array
            if ('extra' === $key && is_array($value)) {
                $event[$key] = self::format($value);
            } else {
                $event[$key] = $this->normalize($value);
            }
        }

        return $event;
    }

    /**
     * Normalize all non-scalar data types (except null) in a string value
     */
    protected function normalize(mixed $value): mixed
    {
        if (is_scalar($value) || null === $value) {
            return $value;
        }

        // better readable JSON
        static $jsonFlags;
        if ($jsonFlags === null) {
            $jsonFlags  = 0;
            $jsonFlags |= defined('JSON_UNESCAPED_SLASHES') ? JSON_UNESCAPED_SLASHES : 0;
            $jsonFlags |= defined('JSON_UNESCAPED_UNICODE') ? JSON_UNESCAPED_UNICODE : 0;
        }

        // Error suppression is used in several of these cases as a fix for each of
        // #5383 and #4616. Without it, #4616 fails whenever recursion occurs during
        // json_encode() operations; usage of a dedicated error handler callback
        // causes #5383 to fail when the Logger is being used as an error handler.
        // The only viable solution here is error suppression, ugly as it may be.
        if ($value instanceof DateTime) {
            $value = $value->format($this->getDateTimeFormat());
        } elseif ($value instanceof Traversable) {
            $value = json_encode(iterator_to_array($value), $jsonFlags);
        } elseif (is_array($value)) {
            $value = json_encode($value, $jsonFlags);
        } elseif (is_object($value) && ! method_exists($value, '__toString')) {
            $value = sprintf('object(%s) %s', $value::class, json_encode($value));
        } elseif (is_resource($value)) {
            $value = sprintf('resource(%s)', get_resource_type($value));
        } elseif (! is_object($value)) {
            $value = gettype($value);
        }

        return (string) $value;
    }

    /**
     * {@inheritDoc}
     */
    public function getDateTimeFormat(): string
    {
        return $this->dateTimeFormat;
    }

    /**
     * {@inheritDoc}
     */
    public function setDateTimeFormat(string $dateTimeFormat): static
    {
        $this->dateTimeFormat = $dateTimeFormat;
        return $this;
    }
}
