/**
 * CategoryTree — reusable collapsible category tree component
 *
 * Usage:
 *   var tree = new CategoryTree({
 *       container:  document.getElementById('myContainer'),
 *       categories: [{id, name, parent_id}, ...],  // flat array
 *       selectedId: 42,           // optional, currently selected
 *       searchable: true,         // optional, show search input (default true)
 *       onSelect:   function(id, name) { ... }  // called on click
 *   });
 *   tree.setSelected(id);   // programmatically change selection
 *   tree.getSelected();     // returns {id, name} or null
 *   tree.destroy();         // remove from DOM
 */
function CategoryTree(opts) {
    this._container  = opts.container;
    this._allCats    = opts.categories || [];
    this._selectedId = opts.selectedId || 0;
    this._searchable = opts.searchable !== false;
    this._onSelect   = opts.onSelect || function() {};
    this._searchVal  = '';

    // Build id→node and parent→children maps
    this._byId    = {};
    this._children = {};  // parent_id → [ids]

    var self = this;
    this._allCats.forEach(function(c) {
        self._byId[c.id] = c;
        var pid = c.parent_id || 0;
        if (!self._children[pid]) self._children[pid] = [];
        self._children[pid].push(c.id);
    });

    // Track open nodes
    this._open = {};  // node id → bool

    // Auto-open path to selected
    if (this._selectedId) {
        this._openPath(this._selectedId);
    } else {
        // Open all top-level nodes by default
        var roots = this._children[0] || [];
        for (var i = 0; i < roots.length; i++) {
            this._open[roots[i]] = true;
        }
    }

    this._render();
}

CategoryTree.prototype._openPath = function(id) {
    var cat = this._byId[id];
    while (cat && cat.parent_id) {
        this._open[cat.parent_id] = true;
        cat = this._byId[cat.parent_id];
    }
    // Also open top-level nodes
    var roots = this._children[0] || [];
    for (var i = 0; i < roots.length; i++) {
        this._open[roots[i]] = true;
    }
};

CategoryTree.prototype._matchTokens = function(name, query) {
    if (!query) return true;
    var tokens = query.toLowerCase().split(/\s+/).filter(function(t) { return t.length > 0; });
    var lname  = (name || '').toLowerCase();
    for (var i = 0; i < tokens.length; i++) {
        if (lname.indexOf(tokens[i]) === -1) return false;
    }
    return true;
};

// Returns set of ids that match search (including ancestors needed to show them)
CategoryTree.prototype._matchingIds = function(query) {
    if (!query) return null; // null = show all
    var self = this;
    var direct = {};
    this._allCats.forEach(function(c) {
        if (self._matchTokens(c.name, query)) direct[c.id] = true;
    });
    // Add all ancestors of matching nodes
    var visible = {};
    Object.keys(direct).forEach(function(id) {
        visible[id] = true;
        var cat = self._byId[id];
        while (cat && cat.parent_id) {
            visible[cat.parent_id] = true;
            cat = self._byId[cat.parent_id];
        }
    });
    return visible;
};

CategoryTree.prototype._render = function() {
    var self = this;
    var c    = this._container;
    c.innerHTML = '';
    c.className = (c.className || '') + ' cat-tree';

    // Search
    if (this._searchable) {
        var sw = document.createElement('div');
        sw.className = 'cat-tree-search-wrap';
        var inp = document.createElement('input');
        inp.type = 'text';
        inp.className = 'cat-tree-search';
        inp.placeholder = 'Пошук категорії...';
        inp.value = this._searchVal;
        inp.addEventListener('input', function() {
            self._searchVal = this.value;
            self._renderNodes();
        });
        sw.appendChild(inp);
        c.appendChild(sw);
        this._searchInput = inp;
    }

    // Nodes wrapper
    var wrap = document.createElement('div');
    wrap.className = 'cat-tree-nodes';
    c.appendChild(wrap);
    this._nodesWrap = wrap;

    this._renderNodes();
};

CategoryTree.prototype._renderNodes = function() {
    var self    = this;
    var wrap    = this._nodesWrap;
    var query   = this._searchVal.trim();
    var visible = this._matchingIds(query);

    wrap.innerHTML = '';

    var roots = this._children[0] || [];
    var found = false;

    roots.forEach(function(rootId) {
        if (visible && !visible[rootId]) return;
        var el = self._buildNode(rootId, 0, visible, query);
        if (el) { wrap.appendChild(el); found = true; }
    });

    if (!found) {
        var empty = document.createElement('div');
        empty.className = 'cat-tree-empty';
        empty.textContent = query ? 'Нічого не знайдено' : 'Немає категорій';
        wrap.appendChild(empty);
    }

    // Scroll selected into view after render
    var sel = wrap.querySelector('.cat-node-row.selected');
    if (sel) setTimeout(function() { sel.scrollIntoView({ block: 'nearest' }); }, 0);
};

CategoryTree.prototype._buildNode = function(id, depth, visible, query) {
    var self     = this;
    var cat      = this._byId[id];
    if (!cat) return null;

    var children  = this._children[id] || [];
    var hasChildren = children.length > 0;
    var isOpen    = !!this._open[id] || (query && visible && visible[id]);
    var isSelected = (id === self._selectedId);

    var node = document.createElement('div');
    node.className = 'cat-node';

    // Row
    var row = document.createElement('div');
    row.className = 'cat-node-row' + (isSelected ? ' selected' : '');
    row.style.paddingLeft = (12 + depth * 18) + 'px';

    // Toggle arrow
    var toggle = document.createElement('span');
    toggle.className = 'cat-node-toggle' + (hasChildren ? (isOpen ? ' open' : '') : ' leaf');
    toggle.innerHTML = '&#9658;'; // ▶
    row.appendChild(toggle);

    // Name
    var nameEl = document.createElement('span');
    nameEl.className = 'cat-node-name';
    nameEl.textContent = cat.name || ('(id:' + id + ')');
    row.appendChild(nameEl);

    node.appendChild(row);

    // Children container
    var childWrap = null;
    if (hasChildren) {
        childWrap = document.createElement('div');
        childWrap.className = 'cat-node-children' + (isOpen ? ' open' : '');
        node.appendChild(childWrap);
    }

    // Toggle click — only on arrow area
    if (hasChildren) {
        toggle.addEventListener('click', function(e) {
            e.stopPropagation();
            var nowOpen = !self._open[id];
            self._open[id] = nowOpen;
            if (nowOpen) {
                toggle.classList.add('open');
                childWrap.classList.add('open');
            } else {
                toggle.classList.remove('open');
                childWrap.classList.remove('open');
            }
        });
    }

    // Row click — select
    row.addEventListener('click', function() {
        // Deselect previous
        var prev = self._nodesWrap.querySelector('.cat-node-row.selected');
        if (prev) prev.classList.remove('selected');
        row.classList.add('selected');
        self._selectedId = id;
        self._onSelect(id, cat.name);
    });

    // Render visible children
    if (hasChildren && childWrap) {
        children.forEach(function(childId) {
            if (visible && !visible[childId]) return;
            var childEl = self._buildNode(childId, depth + 1, visible, query);
            if (childEl) childWrap.appendChild(childEl);
        });
    }

    return node;
};

CategoryTree.prototype.setSelected = function(id) {
    this._selectedId = id || 0;
    if (id) this._openPath(id);
    this._renderNodes();
};

CategoryTree.prototype.getSelected = function() {
    if (!this._selectedId) return null;
    var cat = this._byId[this._selectedId];
    return cat ? { id: cat.id, name: cat.name } : null;
};

CategoryTree.prototype.focus = function() {
    if (this._searchInput) this._searchInput.focus();
};

CategoryTree.prototype.destroy = function() {
    this._container.innerHTML = '';
};
