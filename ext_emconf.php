<?php
$EM_CONF[$_EXTKEY] = [
  'title' => 'Inline FAL relation fix for FlexForm fields',
  'description' => 'Fix for a very specific FAL related problem: FAL relations to sys_file_reference inside FlexForm fields duplicate relations of original and leave no relations on copies when copying parent in a workspace',
  'category' => 'misc',
  'author' => 'Claus Due',
  'author_email' => 'claus@namelesscoder.net',
  'author_company' => '',
  'shy' => '',
  'dependencies' => '',
  'conflicts' => '',
  'priority' => '',
  'module' => '',
  'state' => 'beta',
  'internal' => '',
  'uploadfolder' => 0,
  'createDirs' => '',
  'modify_tables' => '',
  'clearCacheOnLoad' => 1,
  'lockType' => '',
  'version' => '1.0.0',
  'constraints' => [
    'depends' => [
      'php' => '5.6.0-7.1.99',
      'typo3' => '8.6.99-9.0.99',
      'workspaces' => '8.6.99-9.0.99'
    ],
    'conflicts' => [],
    'suggests' => [],
  ],
  'suggests' => [],
  '_md5_values_when_last_written' => '',
];
