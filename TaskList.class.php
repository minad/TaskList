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
			if (!$title->isSubpage() && $title->getText() != wfMsg('tasklist-overview')) {
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
			$fields[trim($x[0])] = trim($x[1]);
		}
		return array(name        => $name,
			     priority    => intval($fields['priority']),
			     user        => ucwords($fields['user']),
			     description => $fields['description'],
			     date        => $fields['date'],
			     status      => $fields['status'],
			     progress    => intval($fields['progress'])
			     );
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
		$url = $title->getLocalURL();
		$deleteURL = $title->getLocalURL('action=delete');
		$editURL = $title->getLocalURL('action=edit');

		$user = '';
		if ($task['user']) {
			$userUrl = Title::makeTitle(NS_USER, $task['user'])->getLocalUrl();
			$user = '<a href="'.$userUrl.'">'.htmlspecialchars($task['user']).'</a>';
		}

		return '<tr class="task priority'.$task['priority'].'"><td class="priority">'.$task['priority'].'</td><td><a href="'.$url.'">'.
			htmlspecialchars($task['name']).'</a></td><td>'.htmlspecialchars(self::truncate($task['description'])).'</td><td>'.htmlspecialchars($task['date']).
			'</td><td>'.$user.'</td><td>'.htmlspecialchars(self::truncate($task['status'])).
			'</td><td><div class="progress" style="width: '.$task['progress'].
			'%">'.$task['progress'].'%</div></td><td class="actions"><a href="'.$deleteURL.'">'.
			self::img('delete').'</a><a href="'.$editURL.'">'.self::img('edit').'</a></td></tr>';
	}

	private static function taskHeader($newTaskUrl) {
		return '<tr class="task"><th>'.wfMsg('tasklist-prio').'</th><th>'.wfMsg('tasklist-name').
			'</th><th>'.wfMsg('tasklist-description').'</th><th>'.wfMsg('tasklist-date').'</th><th>'
			.wfMsg('tasklist-user').'</th><th>'.wfMsg('tasklist-status').'</th><th>'.wfMsg('tasklist-progress').
			'</th><th class="unsortable"><a href="'.$newTaskUrl.'">'.self::img('add').'</a></th></tr>';
	}

	private static function allTasks() {
		global $wgExtraNamespaces;

		$projects = self::getProjects();

		$newProjectUrl = Title::makeTitle(NS_SPECIAL, wfMsg('newproject'))->getLocalURL();

		$text = '<ul class="taskmenu"></li><li><a href="'.$newProjectUrl.'">'.wfMsg('newproject'). '</a></li></ul>';

		if (empty($projects)) {
			$text .= '<p>' . wfMsg('tasklist-no-projects') . '</p>';
			return $text;
		}

		$text .= '<table id="projectlist">';

		foreach ($projects as $projectName => $tasks) {
			$projectUrl = Title::makeTitle(NS_TASKS, $projectName)->getLocalURL();

			$projectPriority = 100;
			foreach ($tasks as $task) {
				if ($task['priority'] < $projectPriority)
					$projectPriority = $task['priority'];
			}

			$newTaskUrl = Title::makeTitle(NS_SPECIAL, wfMsg('newtask').'/'.$projectName)->getLocalURL();
			$text .= '<tr class="project priority'.$projectPriority.'"><th class="priority"></th><th colspan="8"><span class="title"><a href="'.$projectUrl.'">'.
				htmlspecialchars($projectName).'</a></span> <span class="count">'. count($tasks).' '.wfMsg('tasklist-task/s').'</span></th></tr>' .
				self::taskHeader($newTaskUrl);

			if (empty($tasks)) {
				$text .= '<tr class="task"><td colspan="8">'.wfMsg('tasklist-no-tasks').'</td></tr>';
			} else {
				foreach ($tasks as $task)
					$text .= self::taskRow($projectName, $task);
			}
			$text .= '<tr class="placeholder"><td colspan="8"></td></tr>';
		}

		$text .= '</table>';

		return $text;
	}

	public static function projectTasks() {
		global $wgTitle, $wgExtraNamespaces;
		$projectName = $wgTitle->getText();
		$tasks = self::getTasks($wgTitle);

		$overviewUrl = Title::makeTitle(NS_TASKS, wfMsg('tasklist-overview'))->getLocalURL();
		$newTaskUrl = Title::makeTitle(NS_SPECIAL, wfMsg('newtask').'/'.$projectName)->getLocalURL();

		$text = '<ul class="taskmenu"><li><a href="'.$overviewUrl.'">'.wfMsg('tasklist-overview').'</a></li><li><a href="'.$newTaskUrl.'">'.wfMsg('newtask').
			'</a></li></ul><table id="tasklist" class="sortable">' . self::taskHeader($newTaskUrl);

		if (empty($tasks)) {
			$text .= '<tr class="task"><td colspan="8">'.wfMsg('tasklist-no-tasks').'</td></tr>';
		} else {
			foreach ($tasks as $task)
				$text .= self::taskRow($projectName, $task);
		}

		$text .= '</table>';

		return $text;
	}

	public static function addStyle() {
		global $wgOut, $wgScriptPath, $wgTaskListPath;
		$wgOut->addLink(array('rel' => 'stylesheet', 'type' => 'text/css', 'href' => "$wgScriptPath$wgTaskListPath/tasklist.css"));
	}

	public static function headerHook(&$article, &$outputDone, &$pcache) {
		global $wgOut, $wgTitle, $wgScriptPath, $wgJsMimeType, $wgTaskListPath;
		if ($wgTitle->getNamespace() == NS_TASKS) {
			self::addStyle();
			$wgOut->addScript("<script type=\"$wgJsMimeType\" src=\"$wgScriptPath$wgTaskListPath/tasklist.js\"></script>\n");
		}
		return true;
	}

	public static function tasksHook($input, $args, $parser) {
		$parser->disableCache();

		global $wgTitle;
		if ($wgTitle->getNamespace() != NS_TASKS || $wgTitle->isSubpage())
			return '';

		if ($wgTitle->getText() == wfMsg('tasklist-overview'))
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
				$task = array(priority    => intval($wgRequest->getText('priority')),
					      user        => ucwords($wgRequest->getText('user')),
					      description => $wgRequest->getText('description'),
					      date        => $wgRequest->getText('date'),
					      status      => $wgRequest->getText('status'),
					      progress    => intval($wgRequest->getText('progress')));
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
						$wgOut->addHTML('<div style="border: 2px solid #A00; padding: 8px; margin: 8px;">' . $editpage->showDiff() . '</div>');
					} else {
						$parserOptions = ParserOptions::newFromUser($wgUser);
						$parserOptions->setEditSection(false);
						$parserOptions->setTidy(true);
						$parserOutput = $wgParser->parse($editpage->textbox1, $title, $parserOptions);
						$wgOut->addHTML('<div style="border: 2px solid #A00; padding: 8px; margin: 8px;">' . $parserOutput->getText() . '</div>');
					}
				}

				$wgOut->addHTML('<form class="taskform" method="post" action="' . $title->escapeLocalURL("action=submit") .
						'"><table><tr><td><label for="priority">' .
						wfMsg('tasklist-priority').':</label></td><td><select id="priority" name="priority" size="1">'.self::optionList(1, 5, 1, $task['priority']).
						'</select></td></tr><tr><td><label for="user">'
						.wfMsg('tasklist-user').':</label></td><td><input id="user" name="user" type="text" size="20" value="'.
						htmlspecialchars($task['user']).'"/></td></tr><tr><td><label for="description">'.wfMsg('tasklist-description').
						':</label></td><td><input id="description" name="description" type="text" size="40" value="'.htmlspecialchars($task['description']).
						'"/></td></tr><tr><td><label for="date">'.wfMsg('tasklist-date').':</label></td><td><input id="date" name="date" type="text" size="10" value="'.
						htmlspecialchars($task['date']).'"/></td></tr><tr><td><label for="status">'.wfMsg('tasklist-status').
						':</label></td><td><input id="status" name="status" type="text" size="40" value="' . htmlspecialchars($task['status']) .
						'"/></td></tr><tr><td><label for="progress">'.wfMsg('tasklist-progress').
						':</label></td><td><select id="progress" name="progress" size="1">'.self::optionList(0,100,10,$task['progress']).
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

		$project = $wgRequest->getText('project');
		if ($wgRequest->wasPosted() && $project) {
			$article = new Article(Title::makeTitle(NS_TASKS, ucwords($project)));
			$article->insertNewArticle('<tasks/>', '', false, false, false, '');
		} else {
			TaskList::addStyle();

			$wgOut->setPageTitle(wfMsg('newproject'));

			$overviewUrl = Title::makeTitle(NS_TASKS, wfMsg('tasklist-overview'))->getLocalURL();

			$wgOut->addHTML('<ul class="taskmenu"><li><a href="'.$overviewUrl.'">'.wfMsg('tasklist-overview').'</a></li></ul>'.
					'<form class="taskform" method="post" action="' . $this->getTitle()->escapeLocalURL() .
					'"><table><tr><td><label for="project">'.wfMsg('tasklist-name').
					':</label></td><td><input id="project" name="project" type="text" size="20"/></td></tr><tr><td colspan="2">'.
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

		$project = $wgRequest->getText('project');
		$name = $wgRequest->getText('name');

		if (!$project)
			$project = $par;

		if ($wgRequest->wasPosted() && $project && $name) {
			$task = array(priority    => intval($wgRequest->getText('priority')),
					user        => ucwords($wgRequest->getText('user')),
					description => $wgRequest->getText('description'),
					date        => $wgRequest->getText('date'),
					status      => $wgRequest->getText('status'),
					progress    => intval($wgRequest->getText('progress')));
			$article = new Article(Title::makeTitle(NS_TASKS, ucwords($project) . '/' . ucwords($name)));
			$article->insertNewArticle(TaskList::formatTask($task), '', false, false, false, '');
		} else {
			TaskList::addStyle();

			$wgOut->setPageTitle(wfMsg('newtask'));

			$overviewUrl = Title::makeTitle(NS_TASKS, wfMsg('tasklist-overview'))->getLocalURL();

			$wgOut->addHTML('<ul class="taskmenu"><li><a href="'.$overviewUrl.'">'.wfMsg('tasklist-overview').'</a></li></ul>'.
					'<form class="taskform" method="post" action="' . $this->getTitle()->escapeLocalURL() . '"><table><tr><td><label for="project">'.
					wfMsg('tasklist-project').':</label></td><td><input id="project" name="project" type="text" size="20" value="'.htmlspecialchars($project).
					'"/></td></tr><tr><td><label for="name">'.wfMsg('tasklist-name').':</label></td><td><input id="name" name="name" type="text" size="20" value="'.
					htmlspecialchars($name).'"/></td></tr><tr><td><label for="priority">'.wfMsg('tasklist-priority').':</label></td><td>'.
					'<select id="priority" name="priority" size="1">'.TaskList::optionList(1,5).'</select></td></tr><tr><td><label for="user">'.wfMsg('tasklist-user').
					':</label></td><td><input id="user" name="user" type="text" size="20"/></td></tr><tr><td><label for="description">'.wfMsg('tasklist-description').
					':</label></td><td><input id="description" name="description" type="text" size="40"/></td></tr><tr><td><label for="date">'.wfMsg('tasklist-date').
					':</label></td><td><input id="date" name="date" type="text" size="10" value="'.date('d.m.y').
					'"/></td></tr><tr><td><label for="status">'.wfMsg('tasklist-status').
					':</label></td><td><input id="status" name="status" type="text" size="40"/></td></tr><tr><td><label for="progress">'.wfMsg('tasklist-progress').
					':</label></td><td><select id="progress" name="progress" size="1">'.TaskList::optionList(0,100,10).'</select></td></tr><tr><td colspan="2">'.
					'<input id="wpSave" name="wpSave" type="submit" value="' . wfMsg('savearticle') . '"/></table></form>');
		}
	}
}
