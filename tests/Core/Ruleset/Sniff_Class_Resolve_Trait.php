<?php

declare(strict_types=1);

namespace Php_code_Sniffer\Tests\Core\Ruleset;

/**
 * Resolve legacy sniff class names to registered Php_code_Sniffer classes.
 */
trait SniffClassResolveTrait
{
    /**
     * @param \Php_code_Sniffer\Ruleset $ruleset Ruleset under test.
     * @param string                   $legacyFqcn Legacy sniff FQCN.
     *
     * @return string
     */
    protected function resolveRegisteredSniffClass($ruleset, $legacyFqcn)
    {
        if (isset($ruleset->sniffs[$legacyFqcn]) === true) {
            return $legacyFqcn;
        }

        $legacyTail = substr($legacyFqcn, strrpos($legacyFqcn, '\\') + 1);
        $legacyStem = preg_replace('/Sniff$/', '', $legacyTail);
        $legacyStem = str_replace('_', '', strtolower($legacyStem));

        foreach ($ruleset->sniffs as $registeredClass => $sniffObject) {
            $registeredTail = substr($registeredClass, strrpos($registeredClass, '\\') + 1);
            $registeredStem = preg_replace('/_Sniff$/', '', $registeredTail);
            $registeredStem = str_replace('_', '', strtolower($registeredStem));
            if ($legacyStem === $registeredStem) {
                return $registeredClass;
            }
        }

        $this->fail('Sniff not registered: '.$legacyFqcn);

    }//end resolveRegisteredSniffClass()
}
