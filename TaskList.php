<?php
if (!defined('MEDIAWIKI')) die();

$wgExtensionCredits['other']['TaskList'] = array(
	'name'           => 'TaskList',
	'author'         => array('Daniel Mendler'),
	'description'    => 'Simple Task manager',
	'url'            => 'http://github.com/minad/TaskList/tree/master'
);

define('NS_TASKS', 300);
define('NS_TASKS_TALK', 301);

$wgTaskListPath = '/extensions/TaskList';
$dir = dirname(__FILE__) . '/';
$wgExtensionMessagesFiles['TaskList'] = $dir . 'TaskList.i18n.php';
require($dir . 'TaskList.class.php');

$wgExtensionFunctions[] = 'efTaskList';

$wgShowExceptionDetails = true;

$wgNamespacesWithSubpages[NS_TASKS] = true;
$wgContentNamespaces[] = NS_TASKS;

# TODO: I18n this
$wgExtraNamespaces[NS_TASKS] = 'Aufgaben';
$wgExtraNamespaces[NS_TASKS_TALK] = 'Aufgaben Diskussion';
$wgNamespaceAliases['Aufgabe'] = NS_TASKS;
$wgNamespaceAliases['Aufgabe Diskussion'] = NS_TASKS_TALK;

$wgSpecialPages['NewTask'] = 'NewTask';
$wgSpecialPages['NewProject'] = 'NewProject';
$wgExtensionAliasesFiles['MyExtension'] = $dir . 'TaskList.alias.php';

function efTaskList() {
	global $wgHooks, $wgExtraNamespaces, $wgNamespaceAliases;

	wfLoadExtensionMessages('TaskList');

	$wgHooks['ParserFirstCallInit'][] = 'TaskList::init';
	$wgHooks['BeforePageDisplay'][] = 'TaskList::headerHook';
	$wgHooks['AlternateEdit'][] = 'TaskList::editHook';
}
