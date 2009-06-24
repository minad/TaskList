<?php
if (!defined('MEDIAWIKI')) die();

class TaskList {

        private function __construct() {
        }

	private static function getProjects() {
		$db = wfGetDB(DB_SLAVE);
		$conds['page_namespace'] = NS_TASKS;
		$pages = TitleArray::newFromResult(
			$db->select('page',
				array('page_id', 'page_namespace', 'page_title', 'page_is_redirect'),
				$conds,
				__METHOD__,
				array()
			)
		);

		$projects = array();
		foreach ($pages as $title) {
			if (!$title->isSubpage() && $title->getText() != wfMsg('tlOverview')) {
				$projectName = $title->getText();
				$projects[$projectName] = self::getTasks($title);
			}
		}
		return $projects;
	}

	public static function truncate($s, $max = 30, $ellipsis = '...') {
		if (strlen($s) <= $max)
			return $s;
		return substr_replace($s, $ellipsis, $max - strlen($ellipsis));
	}

	public static function formatTask($task) {
		return "{{Task\n|priority={$task['priority']}\n|user={$task['user']}\n|description={$task['description']}\n" .
			"|date={$task['date']}\n|status={$task['status']}\n|progress={$task['progress']}}}";
	}

	private static function parseTask($name, $content) {
		preg_match('/\{\{Task(.*?)\}\}/s', $content, $matches);
		$matches = preg_split('/\s*\|\s*/', $matches[1]);
		$fields = array();
		foreach ($matches as $match) {
			$x = preg_split('/=/', $match);
			$fields[trim(strtolower($x[0]))] = trim($x[1]);
		}
		return array(name        => $name,
			     priority    => intval($fields['priority']),
			     user        => self::fixName($fields['user']),
			     description => $fields['description'],
			     date        => $fields['date'],
			     status      => $fields['status'],
			     progress    => intval($fields['progress'])
			     );
	}

	public static function fixName($name) {
		$name = trim($name);
		$name = strtoupper(substr($name, 0, 1)) . substr($name, 1);
		return $name;
	}

	public static function cmpTask($a, $b) {
		if ($a['priority'] != $b['priority'])
			return $a['priority'] < $b['priority'] ? -1 : 1;
		if ($a['name'] != $b['name'])
			return $a['name'] < $b['name'] ? -1 : 1;
		return 0;
	}

	private static function getTasks($parentTitle) {
		$tasks = array();
		foreach ($parentTitle->getSubpages() as $title) {
			$taskName = $title->getSubpageText();
			$article = new Article($title, 0);
			$content = $article->getContent();
			$tasks[] = self::parseTask($taskName, $content);
		}
		usort($tasks, array('TaskList', 'cmpTask'));
		return $tasks;
	}

	private static function img($name) {
		global $wgScriptPath, $wgTaskListPath;
		return "<img src=\"$wgScriptPath$wgTaskListPath/images/$name.png\" alt=\"$name\"/>";
	}

	private static function taskRow($projectName, $task) {
		$title = Title::makeTitle(NS_TASKS, $projectName.'/'.$task['name']);
		$url = $title->getLocalUrl();
		$deleteURL = $title->getLocalUrl('action=delete');
		$editURL = $title->getLocalUrl('action=edit');

		$user = '';
		if ($task['user']) {
			$userUrl = Title::makeTitle(NS_USER, $task['user'])->getLocalUrl();
			$user = '<a href="'.$userUrl.'">'.htmlspecialchars($task['user']).'</a>';
		}

		return '<tr class="tlTask tlPriority'.$task['priority'].'"><td class="tlPriority">'.$task['priority'].
			'</td><td><a href="'.$url.'">'.htmlspecialchars($task['name']).'</a></td><td>'.
			htmlspecialchars(self::truncate($task['description'])).'</td><td>'.
			htmlspecialchars($task['date']).'</td><td>'.$user.'</td><td>'.htmlspecialchars(self::truncate($task['status'])).
			'</td><td><div class="tlProgress" style="width: '.$task['progress'].
			'%">'.$task['progress'].'%</div></td><td class="tlActions"><a href="'.$deleteURL.'">'.
			self::img('delete').'</a><a href="'.$editURL.'">'.self::img('edit').'</a></td></tr>';
	}

	private static function taskHeader($newTaskUrl) {
		return '<tr class="tlTask"><th>'.wfMsg('tlPrio').'</th><th>'.wfMsg('tlName').
			'</th><th>'.wfMsg('tlDescription').'</th><th>'.wfMsg('tlDate').'</th><th>'
			.wfMsg('tlUser').'</th><th>'.wfMsg('tlStatus').'</th><th>'.wfMsg('tlProgress').
			'</th><th class="unsortable"><a href="'.$newTaskUrl.'">'.self::img('add').'</a></th></tr>';
	}

	private static function projectPriority($tasks) {
		$prio = 100;
		foreach ($tasks as $task) {
			if ($task['priority'] < $prio)
				$prio = $task['priority'];
		}
		return $prio;
	}

	private static function allTasks() {
		$projects = self::getProjects();

		$newProjectUrl = Title::makeTitle(NS_SPECIAL, wfMsg('newproject'))->getLocalUrl();

		$text = '<ul class="tlMenu"></li><li><a href="'.$newProjectUrl.'">'.wfMsg('newproject'). '</a></li></ul>';

		if (empty($projects)) {
			$text .= '<p>' . wfMsg('tlNoProjects') . '</p>';
			return $text;
		}

		$text .= '<table class="tlProjects" id="tlProjects">';

		foreach ($projects as $projectName => $tasks) {
			$projectUrl = Title::makeTitle(NS_TASKS, $projectName)->getLocalUrl();

			$newTaskUrl = Title::makeTitle(NS_SPECIAL, wfMsg('newtask').'/'.$projectName)->getLocalUrl();
			$text .= '<tr class="tlProject tlPriority'.self::projectPriority($tasks).
				'"><th class="tlPriority"></th><th colspan="8"><span class="tlTitle"><a href="'.$projectUrl.'">'.
				htmlspecialchars($projectName).'</a></span> <span class="tlCount">'. count($tasks).' '.
				wfMsg('tlTask/s').'</span></th></tr>'.self::taskHeader($newTaskUrl);

			if (empty($tasks)) {
				$text .= '<tr class="tlTask"><td colspan="8">'.wfMsg('tlNoTasks').'</td></tr>';
			} else {
				foreach ($tasks as $task)
					$text .= self::taskRow($projectName, $task);
			}
			$text .= '<tr class="tlPlaceholder"><td colspan="8"></td></tr>';
		}

		$text .= '</table>';

		return $text;
	}

	public static function projectOverview() {
		$projects = self::getProjects();

		if (empty($projects))
			return '<p>' . wfMsg('tlNoProjects') . '</p>';

		$text .= '<table class="tlProjects">';

		foreach ($projects as $projectName => $tasks) {
			$projectUrl = Title::makeTitle(NS_TASKS, $projectName)->getLocalUrl();

			$text .= '<tr class="tlProject tlPriority'.self::projectPriority($tasks).
				'"><th class="tlPriority"></th><th colspan="8"><span class="tlTitle"><a href="'.
				$projectUrl.'">'.htmlspecialchars($projectName).'</a></span> <span class="tlCount">'.
				count($tasks).' '.wfMsg('tlTask/s').'</span></th></tr>';
		}

		$text .= '</table>';

		return $text;
	}

	public static function projectTasks() {
		global $wgTitle;
		$projectName = $wgTitle->getText();
		$tasks = self::getTasks($wgTitle);

		$overviewUrl = Title::makeTitle(NS_TASKS, wfMsg('tlOverview'))->getLocalUrl();
		$newTaskUrl = Title::makeTitle(NS_SPECIAL, wfMsg('newtask').'/'.$projectName)->getLocalUrl();

		$text = '<ul class="tlMenu"><li><a href="'.$overviewUrl.'">'.wfMsg('tlOverview').
			'</a></li><li><a href="'.$newTaskUrl.'">'.wfMsg('newtask').
			'</a></li></ul><table class="tlTasks sortable">' . self::taskHeader($newTaskUrl);

		if (empty($tasks)) {
			$text .= '<tr class="tlTask"><td colspan="8">'.wfMsg('tlNoTasks').'</td></tr>';
		} else {
			foreach ($tasks as $task)
				$text .= self::taskRow($projectName, $task);
		}

		$text .= '</table>';

		return $text;
	}

	public static function headerHook(&$out, &$sk) {
		global $wgScriptPath, $wgJsMimeType, $wgTaskListPath;
		$out->addLink(array('rel' => 'stylesheet', 'type' => 'text/css', 'href' => "$wgScriptPath$wgTaskListPath/tasklist.css"));
		$out->addScript("<script type=\"$wgJsMimeType\" src=\"$wgScriptPath$wgTaskListPath/tasklist.js\"></script>\n");
		return true;
	}

	public static function tasksHook($input, $args, $parser) {
		$parser->disableCache();

		global $wgTitle;
		if ($wgTitle->getNamespace() != NS_TASKS || $wgTitle->isSubpage())
			return self::projectOverview();

		if ($wgTitle->getText() == wfMsg('tlOverview'))
			return self::allTasks();
		else
			return self::projectTasks();
	}

	public static function optionList($min, $max, $step = 1, $selected = 0) {
		$text = '';
		for ($i=$min; $i<=$max; $i += $step)
			$text .= $i == $selected ? "<option selected=\"selected\">$i</option>" : "<option>$i</option>";
		return $text;
	}

	public static function editHook(&$editpage) {
		global $wgRequest, $wgOut, $wgParser, $wgUser;

		$title = $editpage->getArticle()->getTitle();
		$article = $editpage->getArticle();

		if ($title->getNamespace() == NS_TASKS && $title->isSubpage()) {
			$task = null;
			if ($wgRequest->wasPosted()) {
				$task = array(priority    => intval($wgRequest->getText('tlPriority')),
					      user        => self::fixName($wgRequest->getText('tlUser')),
					      description => $wgRequest->getText('tlDescription'),
					      date        => $wgRequest->getText('tlDate'),
					      status      => $wgRequest->getText('tlStatus'),
					      progress    => intval($wgRequest->getText('tlProgress')));
			} elseif ($article->exists()) {
				$task = self::parseTask(null, $article->getContent());
			}

			$editpage->textbox1 = self::formatTask($task);

			$wgOut->setPageTitle(wfMsg( 'editing', $title->getPrefixedText() ) );

			if ($wgRequest->getCheck('wpSave')) {
				if( $title->exists() )
					$article->updateArticle( $editpage->textbox1, '', false,  false, false, '' );
				else
					$article->insertNewArticle( $editpage->textbox1, '', false, false, false, '');
			} else {
				if ($wgRequest->wasPosted()) {
					if ($wgRequest->getCheck('wpDiff')) {
						$wgOut->addHTML('<div class="tlPreview">'.$editpage->showDiff().'</div>');
					} else {
						$parserOptions = ParserOptions::newFromUser($wgUser);
						$parserOptions->setEditSection(false);
						$parserOptions->setTidy(true);
						$parserOutput = $wgParser->parse($editpage->textbox1, $title, $parserOptions);
						$wgOut->addHTML('<div style="preview-box">'.$parserOutput->getText().'</div>');
					}
				}

				$wgOut->addHTML('<form class="tlForm" method="post" action="' . $title->escapeLocalURL("action=submit") .
						'"><table><tr><td><label for="tlPriority">' .
						wfMsg('tlPriority').':</label></td><td><select id="tlPriority" name="tlPriority" size="1">'.
						self::optionList(1, 5, 1, $task['priority']).
						'</select></td></tr><tr><td><label for="tlUser">'
						.wfMsg('tlUser').':</label></td><td><input id="tlUser" name="tlUser" type="text" size="20" value="'.
						htmlspecialchars($task['user']).'"/></td></tr><tr><td><label for="tlDescription">'.wfMsg('tlDescription').
						':</label></td><td><input id="tlDescription" name="tlDescription" type="text" size="40" value="'.
						htmlspecialchars($task['description']).'"/></td></tr><tr><td><label for="tlDate">'.wfMsg('tlDate').
						':</label></td><td><input id="tlDate" name="tlDate" type="text" size="10" value="'.
						htmlspecialchars($task['date']).'"/></td></tr><tr><td><label for="tlStatus">'.wfMsg('tlStatus').
						':</label></td><td><input id="tlStatus" name="tlStatus" type="text" size="40" value="' . htmlspecialchars($task['status']) .
						'"/></td></tr><tr><td><label for="tlProgress">'.wfMsg('tlProgress').
						':</label></td><td><select id="tlProgress" name="tlProgress" size="1">'.self::optionList(0,100,10,$task['progress']).
						'</select></td></tr><tr><td colspan="2"><input id="wpSave" name="wpSave" type="submit" value="' . wfMsg('savearticle')
						. '"/><input id="wpPreview" name="wpPreview" type="submit" value="' . wfMsg('showpreview') .
						'"/><input id="wpDiff" name="wpDiff" type="submit" value="' . wfMsg('showdiff') . '"/></td></tr></table></form>');
			}


			return false;
		}

		return true;
	}

	public static function init() {
		global $wgParser;
		$wgParser->setHook('tasks', array('TaskList', 'tasksHook'));
		return true;
        }
}

class NewProject extends SpecialPage {
	function __construct() {
		parent::__construct('NewProject');
	}

	function execute($par) {
		global $wgRequest, $wgOut;

		$project = $wgRequest->getText('tlProject');
		if ($wgRequest->wasPosted() && $project && $title = Title::makeTitleSafe(NS_TASKS, TaskList::fixName($project))) {
			$article = new Article($title);
			$article->insertNewArticle('<tasks/>', '', false, false, false, '');
		} else {
			$wgOut->setPageTitle(wfMsg('newproject'));

			$overviewUrl = Title::makeTitle(NS_TASKS, wfMsg('tlOverview'))->getLocalUrl();

			$wgOut->addHTML('<ul class="tlMenu"><li><a href="'.$overviewUrl.'">'.wfMsg('tlOverview').'</a></li></ul>'.
					'<form class="tlForm" method="post" action="' . $this->getTitle()->escapeLocalURL() .
					'"><table><tr><td><label for="tlProject">'.wfMsg('tlName').
					':</label></td><td><input id="tlProject" name="tlProject" type="text" size="20"/></td></tr><tr><td colspan="2">'.
					'<input id="wpSave" name="wpSave" type="submit" value="' . wfMsg('savearticle') . '"/></td></tr></table></form>');
		}
	}
}

class NewTask extends SpecialPage {
	function __construct() {
		parent::__construct('NewTask');
	}

	function execute($par) {
		global $wgRequest, $wgOut;

		$project = $wgRequest->getText('tlProject');
		$name = $wgRequest->getText('tlName');

		if (!$project)
			$project = $par;

		if ($wgRequest->wasPosted() && $project && $name &&
		    $title = Title::makeTitleSafe(NS_TASKS, TaskList::fixName($project) . '/' . TaskList::fixName($name))) {
			$task = array(priority    => intval($wgRequest->getText('tlPriority')),
				      user        => TaskList::fixName($wgRequest->getText('tlUser')),
				      description => $wgRequest->getText('tlDescription'),
				      date        => $wgRequest->getText('tlDate'),
				      status      => $wgRequest->getText('tlStatus'),
				      progress    => intval($wgRequest->getText('tlProgress')));
			$article = new Article($title);
			$article->insertNewArticle(TaskList::formatTask($task), '', false, false, false, '');
		} else {
			$wgOut->setPageTitle(wfMsg('newtask'));

			$overviewUrl = Title::makeTitle(NS_TASKS, wfMsg('tlOverview'))->getLocalUrl();

			$wgOut->addHTML('<ul class="tlMenu"><li><a href="'.$overviewUrl.'">'.wfMsg('tlOverview').'</a></li></ul>'.
					'<form class="tlForm" method="post" action="' . $this->getTitle()->escapeLocalURL() . '"><table><tr><td><label for="tlProject">'.
					wfMsg('tlProject').':</label></td><td><input id="tlProject" name="tlProject" type="text" size="20" value="'.
					htmlspecialchars($project).'"/></td></tr><tr><td><label for="tlName">'.wfMsg('tlName').
					':</label></td><td><input id="tlName" name="tlName" type="text" size="20" value="'.
					htmlspecialchars($name).'"/></td></tr><tr><td><label for="tlPriority">'.wfMsg('tlPriority').':</label></td><td>'.
					'<select id="tlPriority" name="tlPriority" size="1">'.TaskList::optionList(1,5).'</select></td></tr><tr><td><label for="tlUser">'.
					wfMsg('tlUser').
					':</label></td><td><input id="tlUser" name="tlUser" type="text" size="20"/></td></tr><tr><td><label for="tlDescription">'.
					wfMsg('tlDescription').
					':</label></td><td><input id="tlDescription" name="tlDescription" type="text" size="40"/></td></tr><tr><td><label for="tlDate">'.
					wfMsg('tlDate').
					':</label></td><td><input id="tlDate" name="tlDate" type="text" size="10" value="'.date('d.m.y').
					'"/></td></tr><tr><td><label for="tlStatus">'.wfMsg('tlStatus').
					':</label></td><td><input id="tlStatus" name="tlStatus" type="text" size="40"/></td></tr><tr><td><label for="tlProgress">'.
					wfMsg('tlProgress').
					':</label></td><td><select id="tlProgress" name="tlProgress" size="1">'.
					TaskList::optionList(0,100,10).'</select></td></tr><tr><td colspan="2">'.
					'<input id="wpSave" name="wpSave" type="submit" value="' . wfMsg('savearticle') . '"/></table></form>');
		}
	}
}
