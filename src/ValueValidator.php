<?php
namespace Hyvor\FilterQ;

use Carbon\Carbon;
use Hyvor\FilterQ\Exceptions\InvalidValueException;

class ValueValidator
{

    /**
     * @template T
     * @param T $value
     * @return T|Carbon
     * @throws InvalidValueException
     */
    public static function validate(Key $key, mixed $value)
    {

        $keyName = $key->getName();

        // first, check direct values
        $supportedValues = $key->getSupportedValues();
        if (is_array($supportedValues)) {

            if (!in_array($value, $supportedValues)) {
                $values = implode(', ', $supportedValues);
                throw new InvalidValueException(
                    "The key $keyName only supports the following values for filtering: $values. '$value' given"
                );
            }

        }

        // next, check value types
        $supportedValueTypes = $key->getSupportedValueTypes();
        if (is_array($supportedValueTypes)) {

            $isValid = false;

            $valueType = gettype($value);

            foreach ($supportedValueTypes as $supportedValueType) {

                if (
                    // scalar
                    ($supportedValueType === 'int' && $valueType === 'integer') ||
                    ($supportedValueType === 'float' && $valueType === 'double') ||
                    ($supportedValueType === 'string' && $valueType === 'string') ||
                    ($supportedValueType === 'bool' && $valueType === 'boolean') ||
                    ($supportedValueType === 'null' && $valueType === 'NULL') ||

                    // functions
                    ($supportedValueType === 'numeric' && is_numeric($value))
                ) {

                    $isValid = true;
                    break;

                } else if ($supportedValueType === 'date') {

                    // Check if the date is valid
                    $date = is_int($value) ? $value : strtotime($value);

                    if ($date !== false) {
                        $isValid = true;
                        // update value to carbon date
                        $value = Carbon::createFromTimestamp($date);
                        break;
                    }

                }

            }

            if (!$isValid) {
                $validTypesString = implode('|', $supportedValueTypes);
                throw new InvalidValueException("Value for $keyName should be one of: $validTypesString");
            }

        }

        return $value;

    }

}