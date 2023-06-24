<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = (new Finder())
    ->exclude('vendor')
    ->in(__DIR__);

return (new Config())
    ->setRules([
        '@Symfony' => true,
        'phpdoc_to_comment' => false,
        'concat_space' => false,
        'phpdoc_summary' => false,
        'yoda_style' => false,
        'not_operator_with_successor_space' => true,
        'return_type_declaration' => ['space_before' => 'one'],
        'global_namespace_import' => true,
        'increment_style' => ['style' => 'post'],
        'nullable_type_declaration_for_default_null_value' => true,
    ])
    ->setFinder($finder)
    ->setUsingCache(false);
