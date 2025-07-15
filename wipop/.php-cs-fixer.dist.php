<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
  ->in(__DIR__)
  ->exclude('vendor');

return (new Config())
  ->setRiskyAllowed(true)
  ->setRules([
    '@PSR12' => true,
    'indentation_type' => true,
    'phpdoc_indent' => true,
    'curly_braces_position' => [
      'functions_opening_brace'          => 'same_line',
      'classes_opening_brace'            => 'same_line',
      'anonymous_classes_opening_brace'  => 'same_line',
      'control_structures_opening_brace' => 'same_line',
      'anonymous_functions_opening_brace' => 'same_line',
    ],
  ])
  ->setFinder($finder);