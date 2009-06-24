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

	private static function parseTask($content) {
		preg_match('/{{Task(\s*\|\s*\w+=[^\|]\s*)+}}}/', $content, $matches);
		print_r($matches);
		$matches = preg_split('/\s*\|\s*/', $matches[1]);
		$fields = array();
		foreach ($matches as $match) {
			$x = preg_split('/=/', $match);
			$fields[strtrim($x[0])] = strtrim($x[1]);
		}
		return array(
			     priority    => intval($fields['priority']),
			     user        => $fields['user'],
			     description => $fields['description'],
			     date        => $fields['date'],
			     status      => $fields['status'],
			     progress    => intval($fields['progress'])
			     );
	}

	private static function getTasks($parentTitle) {
		$tasks = array();
		foreach ($parentTitle->getSubpages() as $title) {
			$taskName = $title->getSubpageText();
			$article = new Article($title, 0);
			$content = $article->getContent();
			print_r self::parseTask($content);
			$xml = new SimpleXMLElement($content);
			$attrs = $xml->attributes();
			$tasks[$taskName] = array(
				priority    => intval($attrs['priority']),
				user        => $attrs['user'],
				description => $attrs['description'],
				date        => $attrs['date'],
				status      => $attrs['status'],
				progress    => intval($attrs['progress'])
			);
		}
		return $tasks;
	}

	private static function allTasks() {
		global $wgExtraNamespaces, $wgScriptPath, $wgTaskListPath;

		$projects = self::getProjects();

		$newTaskUrl = Title::makeTitle(NS_SPECIAL, "Neue Aufgabe")->getLocalURL();
		$newProjectUrl = Title::makeTitle(NS_SPECIAL, "Neues Projekt")->getLocalURL();

		$text = <<< END
<a href="$newTaskUrl">Neue Aufgabe</a> | <a href="$newProjectUrl">Neues Projekt</a>
<table class="projects">
END;

		foreach ($projects as $projectName => $tasks) {
			$projectUrl = Title::makeTitle(NS_TASKS, $projectName)->getLocalURL();

			$projectPriority = 100;
			foreach ($tasks as $name => $task) {
				if ($task['priority'] < $projectPriority)
					$projectPriority = $task['priority'];
			}

			$text .= <<< END
<tr><td class="priority$projectPriority">$projectPriority</td><td class="title"><a href="$projectUrl">$projectName</a></td></tr>
<tr><td colspan="2">
<table class="tasks sortable">
<tr>
  <th>Priorität</th>
  <th>Name</th>
  <th>Beschreibung</th>
  <th>Erstellungsdatum</th>
  <th>Status</th>
  <th>Fortschritt</th>
  <th></th>
</tr>
END;


			foreach ($tasks as $name => $task) {
				$title = Title::makeTitle(NS_TASKS, "$projectName/$name");
				$url = $title->getLocalURL();
				$deleteURL = $title->getLocalURL('action=delete');
				$deleteImg = "$wgScriptPath$wgTaskListPath/images/delete.png";
				$editURL = $title->getLocalURL('action=edit');
				$editImg = "$wgScriptPath$wgTaskListPath/images/edit.png";

				$text .= <<< END
<tr class="task">
  <td class="priority{$task['priority']}">{$task['priority']}</td>
  <td><a href="$url">$name</a></td>
  <td>{$task['description']}</td>
  <td>{$task['date']}</td>
  <td>{$task['status']}</td>
  <td>{$task['progress']}</td>
  <td><a href="$deleteURL"><img src="$deleteImg"/></a><a href="$editURL"><img src="$editImg"/></a></td>
</tr>
END;
			}

			$text .= '</table></td></tr>';
		}

		$text .= '</table>';

		return $text;
	}

	public static function projectTasks() {
		global $wgTitle, $wgExtraNamespaces, $wgScriptPath, $wgTaskListPath;
		$projectName = $wgTitle->getText();
		$tasks = self::getTasks($wgTitle);

		$overviewUrl = Title::makeTitle(NS_TASKS, wfMsg('tasklist-overview'))->getLocalURL();
		$newTaskUrl = Title::makeTitle(NS_SPECIAL, "Neue Aufgabe/$projectName")->getLocalURL();

		$text = <<< END
<a href="$overviewUrl">Übersicht</a> | <a href="$newTaskUrl">Neue Aufgabe</a>
<table class="tasks sortable">
<tr>
  <th>Priorität</th>
  <th>Name</th>
  <th>Beschreibung</th>
  <th>Erstellungsdatum</th>
  <th>Status</th>
  <th>Fortschritt</th>
  <th></th>
</tr>
END;

		foreach ($tasks as $name => $task) {
			$title = Title::makeTitle(NS_TASKS, "$projectName/$name");
			$url = $title->getLocalURL();
			$deleteURL = $title->getLocalURL('action=delete');
			$deleteImg = "$wgScriptPath$wgTaskListPath/images/delete.png";
			$editURL = $title->getLocalURL('action=edit');
			$editImg = "$wgScriptPath$wgTaskListPath/images/edit.png";

			$text .= <<< END
<tr class="task">
  <td class="priority{$task['priority']}">{$task['priority']}</td>
  <td><a href="$url">$name</a></td>
  <td>{$task['description']}</td>
  <td>{$task['date']}</td>
  <td>{$task['status']}</td>
  <td>{$task['progress']}</td>
  <td><a href="$deleteURL"><img src="$deleteImg"/></a><a href="$editURL"><img src="$editImg"/></a></td>
</tr>
END;
		}

		$text .= '</table>';

		return $text;
	}

/* 	public static function taskHook($input, $args, $parser) { */
/* 		$parser->disableCache(); */

/* 		global $wgTitle; */
/* 		if ($wgTitle->getNamespace() != NS_TASKS || !$wgTitle->isSubpage()) */
/* 			return ''; */

/* 		$text = <<< END */
/* {| class="task"   */
/* | Priorität: */
/* | {$args['priority']} */
/* |- */
/* | Verantwortlicher: */
/* | [[Benutzer:{$args['user']}|{$args['user']}]] */
/* |-   */
/* | Beschreibung: */
/* | {$args['description']} */
/* |- */
/* | Erstellungsdatum: */
/* | {$args['date']} */
/* |- */
/* | Status: */
/* | {$args['status']} */
/* |- */
/* | Fortschritt: */
/* | {$args['progress']} */
/* |} */
/* END; */

/* 		return $parser->recursiveTagParse($text); */
/* 	} */

	public static function headerHook(&$article, &$outputDone, &$pcache) {
		global $wgOut, $wgTitle, $wgScriptPath, $wgJsMimeType, $wgTaskListPath;

		if ($wgTitle->getNamespace() == NS_TASKS) {
			$wgOut->addLink(
				array(
					'rel' => 'stylesheet',
					'type' => 'text/css',
					'href' => "$wgScriptPath$wgTaskListPath/tasklist.css"
				)
			);

			$wgOut->addScript("<script type=\"{$wgJsMimeType}\" src=\"{$wgScriptPath}{$wgTaskListPath}/tasklist.js\"></script>\n");
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

	public static function editHook(&$editpage) {
		global $wgRequest, $wgOut, $wgParser, $wgUser;

		if ($editpage->mTitle->getNamespace() == NS_TASKS && $editpage->mTitle->isSubpage()) {
			$fields = array(
				'priority' => 0,
				'user' => '',
				'description' => '',
				'date' => '',
				'status' => '',
				'progress' => 0,
			);

			if ($wgRequest->wasPosted()) {
				foreach ($fields as $key => $value) {
					$fields[$key] = $wgRequest->getText($key);
				}
			} elseif ($editpage->mArticle->exists()) {
				$xml = new SimpleXMLElement($editpage->mArticle->getContent());
				$attrs  = $xml->attributes();
				foreach ($fields as $key => $value) {
					$fields[$key] = $attrs[$key];
				}
			}

			$editpage->textbox1 = <<< END
{{Task
|priority={$fields['priority']
|user={$fields['user']}
|description={$fields['description']}
|date={$fields['date']}
|status={$fields['status']}
|progress={$fields['progress']}}}
END;

			$wgOut->setPageTitle(wfMsg( 'editing', $editpage->mTitle->getPrefixedText() ) );

			if ($wgRequest->getCheck('wpSave')) {
				if( $editpage->mTitle->exists() )
					$editpage->mArticle->updateArticle( $editpage->textbox1, '', false,  false, false, '' );
				else
					$editpage->mArticle->insertNewArticle( $editpage->textbox1, '', false, false, false, '');
			} else {
				if ($wgRequest->wasPosted()) {
					if ($wgRequest->getCheck('wpDiff')) {
						$wgOut->addHTML('<div style="border: 2px solid #A00; padding: 8px; margin: 8px;">');
						$wgOut->addHTML( $editpage->showDiff());
						$wgOut->addHTML('</div>');
					} else {
						$parserOptions = ParserOptions::newFromUser( $wgUser );
						$parserOptions->setEditSection( false );
						$parserOptions->setTidy(true);
						$parserOutput = $wgParser->parse( $editpage->textbox1, $editpage->mTitle, $parserOptions );
						$previewHTML = $parserOutput->getText();
						$wgOut->addHTML('<div style="border: 2px solid #A00; padding: 8px; margin: 8px;">');
						$wgOut->addHTML($previewHTML);
						$wgOut->addHTML('</div>');
					}
				}


				$wgOut->addHTML('<form id="editform" name="editform" method="post" action="' . $editpage->mTitle->escapeLocalURL("action=submit") . '" enctype="multipart/form-data">');
				$wgOut->addHTML('<table border="0">');

				$wgOut->addHTML('<tr><td>Priorität:</td><td><input id="priority" name="priority" type="text" size="20" value="' . $fields['priority'] . '"/></td></tr>' . "\n");
				$wgOut->addHTML('<tr><td>Verantwortlicher:</td><td><input id="user" name="user" type="text" size="20" value="' . $fields['user'] . '"/></td></tr>' . "\n");
				$wgOut->addHTML('<tr><td>Beschreibung:</td><td><input id="description" name="description" type="text" size="20" value="' . $fields['description'] .'"/></td></tr>' . "\n");
				$wgOut->addHTML('<tr><td>Datum:</td><td><input id="date" name="date" type="text" size="20" value="' . $fields['date'] . '"/></td></tr>' . "\n");
				$wgOut->addHTML('<tr><td>Status:</td><td><input id="status" name="status" type="text" size="20" value="' . $fields['status'] . '"/></td></tr>' . "\n");
				$wgOut->addHTML('<tr><td>Fortschritt:</td><td><input id="progress" name="progress" type="text" size="20" value="' . $fields['progress'] . '"/></td></tr>' . "\n");


				$wgOut->addHTML('</table>');
				$wgOut->addHTML('<input id="wpSave" name="wpSave" type="submit" value="' . wfMsg('savearticle') . '"/>');
				$wgOut->addHTML('<input id="wpPreview" name="wpPreview" type="submit" value="' . wfMsg('showpreview') . '"/> ');
				$wgOut->addHTML('<input id="wpDiff" name="wpDiff" type="submit" value="' . wfMsg('showdiff') . '"/> ');
				$wgOut->addHTML('</form>');
			}


			return false;
		}

		return true;
	}

	public static function init() {
		global $wgParser;
		//$wgParser->setHook('task', array('TaskList', 'taskHook'));
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
			$wgOut->setPageTitle(wfMsg('newproject'));

			$wgOut->addHTML('<form id="editform" name="editform" method="post" action="' . $this->getTitle()->escapeLocalURL() . '" enctype="multipart/form-data">');
			$wgOut->addHTML('<table border="0">');

			$wgOut->addHTML('<tr><td>Projekt:</td><td><input id="project" name="project" type="text" size="20"/></td></tr>' . "\n");

			$wgOut->addHTML('</table>');
			$wgOut->addHTML('<input id="wpSave" name="wpSave" type="submit" value="' . wfMsg('savearticle') . '"/>');
			$wgOut->addHTML('</form>');
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
			$fields = array(
				'priority' => 0,
				'user' => '',
				'description' => '',
				'date' => '',
				'status' => '',
				'progress' => 0,
			);
			foreach ($fields as $key => $value) {
				$fields[$key] = $wgRequest->getText($key);
			}

			$text = <<< END
<task priority="{$fields['priority']}"
      user="{$fields['user']}"
      description="{$fields['description']}"
      date="{$fields['date']}"
      status="{$fields['status']}"
      progress="{$fields['progress']}"/>
END;
			$article = new Article(Title::makeTitle(NS_TASKS, ucwords($project . '/' . $name)));
			$article->insertNewArticle($text, '', false, false, false, '');
		} else {
			$wgOut->setPageTitle(wfMsg('newtask'));

			$wgOut->addHTML('<form id="editform" name="editform" method="post" action="' . $this->getTitle()->escapeLocalURL() . '" enctype="multipart/form-data">');
			$wgOut->addHTML('<table border="0">');

			$wgOut->addHTML('<tr><td>Projekt:</td><td><input id="project" name="project" type="text" size="20" value="' . $project . '"/></td></tr>' . "\n");
			$wgOut->addHTML('<tr><td>Name:</td><td><input id="name" name="name" type="text" size="20" value="' . $name . '"/></td></tr>' . "\n");
			$wgOut->addHTML('<tr><td>Priorität:</td><td><input id="priority" name="priority" type="text" size="20"/></td></tr>' . "\n");
			$wgOut->addHTML('<tr><td>Verantwortlicher:</td><td><input id="user" name="user" type="text" size="20"/></td></tr>' . "\n");
			$wgOut->addHTML('<tr><td>Beschreibung:</td><td><input id="description" name="description" type="text" size="20"/></td></tr>' . "\n");
			$wgOut->addHTML('<tr><td>Datum:</td><td><input id="date" name="date" type="text" size="20"/></td></tr>' . "\n");
			$wgOut->addHTML('<tr><td>Status:</td><td><input id="status" name="status" type="text" size="20"/></td></tr>' . "\n");
			$wgOut->addHTML('<tr><td>Fortschritt:</td><td><input id="progress" name="progress" type="text" size="20"/></td></tr>' . "\n");

			$wgOut->addHTML('</table>');
			$wgOut->addHTML('<input id="wpSave" name="wpSave" type="submit" value="' . wfMsg('savearticle') . '"/>');
			$wgOut->addHTML('</form>');
		}
	}
}
