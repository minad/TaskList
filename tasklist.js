function taskListCollapse(element) {
    collapsed = element.collapsed = !element.collapsed;
    element.setAttribute('class', collapsed ? 'project' : 'project selected');
    element = element.nextSibling;
    while (element) {
	if (element.nodeType == 1 && element.nodeName == 'TR') {
	    if (element.getAttribute('class').indexOf('project') != -1)
		break;
	    element.style.display = collapsed ? 'none' : 'table-row';
	}
	element = element.nextSibling;
    }
}

taskListLast = null;

function taskListToggle() {
    taskListCollapse(this);
    if (taskListLast && taskListLast != this)
	taskListCollapse(taskListLast);
    taskListLast = this.collapsed ? null : this;
}

function taskListInit() {
    element = document.getElementById('projectlist');
    children = element.getElementsByTagName('tr');
    for (i = 0; i < children.length; ++i) {
	child = children[i];
	if (child.getAttribute('class').indexOf('project') != -1) {
	    child.collapsed = true;
	    child.onclick = taskListToggle;
	} else
	    child.style.display = 'none';
    }
}

addOnloadHook(taskListInit);
