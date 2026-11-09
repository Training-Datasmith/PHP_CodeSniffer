<?php

declare(strict_types=1);

namespace Php_code_Sniffer\Tests;

/**
 * Polyfill for PHPUnit assertArraySubset() removed in PHPUnit 9+.
 */
trait AssertArraySubsetTrait
{
    /**
     * @param array       $subset                 Subset expected to be contained in $array.
     * @param array       $array                  Array to compare against.
     * @param bool        $checkForObjectIdentity Not used; kept for signature compatibility.
     * @param string|null $message                Optional assertion message.
     *
     * @return void
     */
    public function assertArraySubset(array $subset, array $array, bool $checkForObjectIdentity = false, ?string $message = ''): void
    {
        foreach ($subset as $key => $value) {
            $this->assertArrayHasKey($key, $array, $message);
            if (is_array($value) === true && is_array($array[$key]) === true) {
                $this->assertArraySubset($value, $array[$key], $checkForObjectIdentity, $message);
            } else {
                $this->assertSame($value, $array[$key], $message);
            }
        }
    }
}
