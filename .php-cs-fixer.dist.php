<?php declare(strict_types = 1);

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->path([
        '#src/#',
        '#tests/#',
    ]);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR1' => true,
        '@PSR2' => true,
        'array_syntax' => [
            'syntax' => 'short',
        ],
        'binary_operator_spaces' => [
            'default' => 'single_space',
        ],
        'declare_strict_types' => true,
        'header_comment' => [
            'header' => '',
        ],
        'method_argument_space' => [
            'keep_multiple_spaces_after_comma' => false,
            'on_multiline' => 'ignore',
        ],
        'non_printable_character' => true,
        'no_blank_lines_after_class_opening' => true,
        'no_trailing_comma_in_list_call' => true,
        'no_trailing_whitespace_in_comment' => false,
        'trailing_comma_in_multiline' => true,
    ])
    ->setFinder($finder);
