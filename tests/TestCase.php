<?php

namespace Volcengine\Tests;

use PHPUnit\Framework\Constraint\RegularExpression;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    /**
     * Asserts that a string matches a given regular expression.
     *
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     * @throws ExpectationFailedException
     *
     * @psalm-template ExpectedType of object
     * @psalm-param class-string<ExpectedType> $expected
     * @psalm-assert =ExpectedType $actual
     */
    public static function assertMatchesRegularExpression(string $pattern, string $string, string $message = ''): void
    {
        if (is_callable("parent::assertMatchesRegularExpression")) {
            parent::assertMatchesRegularExpression($pattern, $string, $message);
        }
        static::assertThat($string, new RegularExpression($pattern), $message);
    }
}
