function taskListCollapse(element) {
    collapsed = element.collapsed = !element.collapsed;
    clazz = element.getAttribute('class');
    if (collapsed)
	clazz = clazz.replace(/\s*tlSelected\s*/, '');
    else
	clazz += ' tlSelected';
    element.setAttribute('class', clazz);
    element = element.nextSibling;
    while (element) {
	if (element.nodeType == 1 && element.nodeName == 'TR') {
	    if (element.getAttribute('class').indexOf('tlProject') != -1)
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
    element = document.getElementById('tlProjects');
    children = element.getElementsByTagName('tr');
    for (i = 0; i < children.length; ++i) {
	child = children[i];
	if (child.getAttribute('class').indexOf('tlProject') != -1) {
	    child.collapsed = true;
	    child.onclick = taskListToggle;
	} else
	    child.style.display = 'none';
    }
}

addOnloadHook(taskListInit);
