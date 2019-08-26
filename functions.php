<?php
declare(strict_types = 1);

use Vinnia\Util\Stack;

if (!function_exists('removeKeysWithValues')) {
    /**
     * @param array $source
     * @param mixed[] $needles
     * @return array
     */
    function removeKeysWithValues(array $source, ...$needles): array
    {
        $stack = new Stack([&$source]);

        while (!$stack->isEmpty()) {
            $parts = $stack->pop();
            $chunk = &$parts[0];

            if (!is_array($chunk)) {
                continue;
            }

            $clone = $chunk;

            foreach ($clone as $key => $value) {
                $matches = in_array($value, $needles, true);

                if (is_array($value) && !$matches) {
                    $stack->push([&$chunk[$key]]);
                    continue;
                }

                if ($matches) {
                    unset($chunk[$key]);
                }
            }
        }

        return $source;
    }
}
