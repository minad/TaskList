(function() {
    function collapse(element) {
	var collapsed = element.collapsed = !element.collapsed;
	var clazz = element.getAttribute('class');
	if (collapsed) {
	    clazz = clazz.replace(/\s*tlSelected\s*/, '');
	} else {
	    clazz += ' tlSelected';
	}
	element.setAttribute('class', clazz);
	element = element.nextSibling;
	while (element) {
	    if (element.nodeType === 1 && element.nodeName === 'TR') {
		if (element.getAttribute('class').indexOf('tlProject') !== -1) {
		    break;
		}
		element.style.display = collapsed ? 'none' : 'table-row';
	    }
	    element = element.nextSibling;
	}
    }

    var currentOpenElement = null;

    function toggle() {
	var x = window.pageXOffset ? window.pageXOffset : document.body.scrollLeft;
	var y = window.pageYOffset ? window.pageYOffset : document.body.scrollTop;
	collapse(this);
	if (currentOpenElement && currentOpenElement !== this) {
	    collapse(currentOpenElement);
	}
	currentOpenElement = this.collapsed ? null : this;
	location.href = this.collapsed ? '#' : this.hash;
	window.scrollTo(x, y);
    }

    function submitElement() {
	this.form.action = location.hash;
	this.form.submit();
    }

    function noSelect() {
	return false;
    }

    function init() {
	var index = 0;
	var openElement = null;
	var elements = document.getElementsByClassName('tlProjects');
	var i, j;

	for (j = 0; j < elements.length; ++j) {
	    var element = elements[j];

	    var children = element.getElementsByTagName('tr');
	    for (i = 0; i < children.length; ++i) {
		var child = children[i];

		if (child.getAttribute('class').indexOf('tlProject') !== -1) {
		    child.hash = '#' + index;
		    child.collapsed = true;
		    child.onclick = toggle;
		    child.onselectstart = noSelect;
		    child.unselectable = 'on';
		    child.style.MozUserSelect = 'none';

		    if (location.hash === child.hash) {
			openElement = child;
		    }
		    ++index;
		} else {
		    child.style.display = 'none';
		}
	    }
	}

	if (openElement) {
	    toggle.call(openElement);
	}

	elements = document.getElementsByClassName('tlFilter');
	for (i = 0; i < elements.length; ++i) {
	    elements[i].onchange = submitElement;
	}
    }

    addOnloadHook(init);
})();
