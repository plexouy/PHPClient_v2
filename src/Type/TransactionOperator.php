<?php
namespace Plexo\Sdk\Type;

class TransactionOperator
{
    const EQUAL                 = 0;
    const NOT_EQUAL             = 1;
    const BIGGER_THAN           = 2;
    const BIGGER_OR_EQUAL_THAN  = 3;
    const SMALLER_THAN          = 4;
    const SMALLER_OR_EQUAL_THAN = 5;
    const CONTAINS              = 6;
    const NOT_CONTAINS          = 7;

    private static $keys = [
        'Equal',
        'NotEqual',
        'BiggerThan',
        'BiggerOrEqualThan',
        'SmallerThan',
        'SmallerOrEqualThen',
        'Contains',
        'NotContains',
    ];

    public function __construct(private $param, private $value)
    {
    }

    public function getParam()
    {
        return $this->param;
    }

    public function getParamKey()
    {
        if (!array_key_exists($this->param, self::$keys)) {
            throw new \Plexo\Sdk\Exception\InvalidArgumentException('Invalid parameter key: ' . $this->param);
        }
        return self::$keys[$this->param];
    }

    public function getValue()
    {
        return $this->value;
    }
}
