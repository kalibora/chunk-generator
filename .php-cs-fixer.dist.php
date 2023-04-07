<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = (new Finder())
    ->exclude('vendor')
    ->in(__DIR__);

return (new Config())
    ->setRules([
        '@Symfony' => true,
        'concat_space' => false,
        'phpdoc_summary' => false,
        'yoda_style' => false,
        'not_operator_with_successor_space' => true,
        'return_type_declaration' => ['space_before' => 'one'],
        'increment_style' => ['style' => 'post'],
    ])
    ->setFinder($finder)
    ->setUsingCache(false);
