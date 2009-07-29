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

function taskListToggle(element) {
    taskListCollapse(element);
    if (taskListLast && taskListLast != element)
	taskListCollapse(taskListLast);
    taskListLast = element.collapsed ? null : element;
    if (element.collapsed)
	location.href = '#none';
    else
	location.href = element.hash;
}

function taskListInit() {
    index = 0;
    openElement = null;
    elements = document.getElementsByClassName('tlProjects');
    for (j = 0; j < elements.length; ++j) {
	element = elements[j];

	children = element.getElementsByTagName('tr');
	for (i = 0; i < children.length; ++i) {
	    child = children[i];

	    if (child.getAttribute('class').indexOf('tlProject') != -1) {
		child.hash = '#' + index;
		child.collapsed = true;
		child.onclick = function() { taskListToggle(this); };

		child.onselectstart = function() {return false;}
		child.unselectable = 'on';
		child.style.MozUserSelect = 'none';

		if (location.hash == child.hash)
		    openElement = child;
		++index;
	    } else
		child.style.display = 'none';
	}
    }
    if (openElement)
	taskListToggle(openElement);

    elements = document.getElementsByClassName('tlFilter');
    for (i = 0; i < elements.length; ++i) {
	elements[i].onchange = function() {
	    this.form.action = location.hash;
	    this.form.submit();
	};
    }
}

addOnloadHook(taskListInit);
