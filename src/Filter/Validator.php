<?php

declare(strict_types=1);

namespace Dot\Log\Filter;

use Dot\Log\Exception\InvalidArgumentException;
use Laminas\Validator\ValidatorInterface as LaminasValidator;
use Traversable;

class Validator implements FilterInterface
{
    /**
     * Regex to match
     */
    protected LaminasValidator $validator;

    /**
     * Filter out any log messages not matching the validator
     */
    public function __construct($validator)
    {
        if ($validator instanceof Traversable && ! $validator instanceof LaminasValidator) {
            $validator = iterator_to_array($validator);
        }
        if (is_array($validator)) {
            $validator = $validator['validator'] ?? null;
        }
        if (! $validator instanceof LaminasValidator) {
            throw new InvalidArgumentException(sprintf(
                'Parameter of type %s is invalid; must implement Laminas\Validator\ValidatorInterface',
                is_object($validator) ? get_class($validator) : gettype($validator)
            ));
        }
        $this->validator = $validator;
    }

    /**
     * Returns TRUE to accept the message, FALSE to block it.
     */
    public function filter(array $event)
    {
        return $this->validator->isValid($event['message']);
    }
}
