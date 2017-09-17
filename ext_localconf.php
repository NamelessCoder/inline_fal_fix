<?php
defined('TYPO3_MODE') or die('Access denied');

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass'][] = \NamelessCoder\InlineFalFix\DataHandlerHook::class;
