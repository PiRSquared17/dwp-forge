var admin_refnotes = (function() {

    function Hash() {
        // copy-pasted from http://www.mojavelinux.com/articles/javascript_hashes.html
        this.length = 0;
        this.items = new Array();

        for (var i = 0; i < arguments.length; i += 2) {
            if (typeof(arguments[i + 1]) != 'undefined') {
                this.items[arguments[i]] = arguments[i + 1];
                this.length++;
            }
        }

        this.removeItem = function(key) {
            if (typeof(this.items[key]) != 'undefined') {
                this.length--;
                delete this.items[key];
            }
        }

        this.getItem = function(key) {
            return this.items[key];
        }

        this.setItem = function(key, value) {
            if (typeof(value) != 'undefined') {
                if (typeof(this.items[key]) == 'undefined') {
                    this.length++;
                }
                this.items[key] = value;
            }
        }

        this.hasItem = function(key) {
            return typeof(this.items[key]) != 'undefined';
        }
    }


    var locale = (function() {
        var lang = new Hash();

        function initialize() {
            var element = $('refnotes-lang');
            if (element != null) {
                var strings = element.innerHTML.split(/:eos:\n/);

                for (var i = 0; i < strings.length; i++) {
                    var match = strings[i].match(/^\s*(\w+) : (.+)/);

                    if (match != null) {
                        lang.setItem(match[1], match[2]);
                    }
                }
            }
        }

        function getString(key) {
            return lang.getItem(key);
        }

        return {
            initialize : initialize,
            getString  : getString
        };
    })();


    var server = (function() {
        var ajax = new sack(DOKU_BASE + '/lib/exe/ajax.php');
        var timer = null;
        var transaction = null;
        var onCompletion = null;

        ajax.encodeURIString = false;

        ajax.onLoading = function() {
            $('field-note-text').value += 'Sending data...\n';
            setStatus(transaction, 'info');
        }

        ajax.onLoaded = function() {
            $('field-note-text').value += 'Data sent.\n';
        }

        ajax.onInteractive = function() {
            $('field-note-text').value += 'Getting data...\n';
        }

        ajax.afterCompletion = function() {
            printResponse();

            if (ajax.responseStatus[0] == '200') {
                onCompletion();
            }
            else {
                setStatus(transaction + '_failed', 'error');
            }

            transaction = null;
            onCompletion = null;
        }

        function printResponse() {
            var e = $('field-note-text');

            e.value += 'Completed.\n';
            e.value += 'URLString sent: ' + ajax.URLString + '\n';

            e.value += 'Status code: ' + ajax.responseStatus[0] + '\n';
            e.value += 'Status message: ' + ajax.responseStatus[1] + '\n';

            e.value += 'Response: "' + ajax.response + '"\n';
        }

        function onLoaded() {
            setStatus('loaded', 'success', 3000);

            var settings = JSON.parse(ajax.response);

            reloadSettings(settings);
        }

        function onSaved() {
            setStatus('saved', 'success', 10000);
        }

        function loadSettings() {
            if (!ajax.failed && (transaction == null)) {
                transaction = 'loading';
                onCompletion = onLoaded;

                ajax.setVar('call', 'refnotes-admin');
                ajax.setVar('action', 'load-settings');
                ajax.runAJAX();
            }
            else {
                setStatus('loading_failed', 'error');
            }
        }

        function saveSettings(settings) {
            if (!ajax.failed && (transaction == null)) {
                transaction = 'saving';
                onCompletion = onSaved;

                ajax.setVar('call', 'refnotes-admin');
                ajax.setVar('action', 'save-settings');

                //TODO: serialize settings

                ajax.runAJAX();
            }
            else {
                setStatus('saving_failed', 'error');
            }
        }

        function setStatus(textId, styleId, timeout) {
            var status = $('server-status');
            status.className   = styleId;
            status.textContent = locale.getString(textId);

            if (typeof(timeout) != 'undefined') {
                timer = window.setTimeout(clearStatus, timeout);
            }
        }

        function clearStatus() {
            setStatus('status', 'cleared');
        }

        return {
            loadSettings : loadSettings,
            saveSettings : saveSettings
        };
    })();


    var namespaces = (function() {

        function DefaultNamespace() {
            this.isReadOnly = function() {
                return true;
            }

            this.getName = function() {
                return '';
            }

            this.getOptionHtml = function() {
                return '';
            }

            this.setStyle = function(name, value) {
            }

            this.getStyle = function(name) {
                return settings.getItem(name);
            }

            this.getStyleInheritance = function(name) {
                return 2;
            }
        }

        function Namespace(name) {
            var style = new Hash();

            function isReadOnly() {
                return false;
            }

            function getName() {
                return name;
            }

            function getOptionHtml() {
                return '<option value="' + name + '">' + name + '</option>';
            }

            function getParent() {
                var parent = name.replace(/\w*:$/, '');

                while (!namespaces.hasItem(parent)) {
                    parent = parent.replace(/\w*:$/, '');
                }

                return namespaces.getItem(parent);
            }

            function setStyle(name, value) {
                if (value == 'inherit') {
                    style.removeItem(name);
                }
                else {
                    style.setItem(name, value);
                }
            }

            function getStyle(name) {
                var result;

                if (style.hasItem(name)) {
                    result = style.getItem(name);
                }
                else {
                    result = getParent().getStyle(name);
                }

                return result;
            }

            function getStyleInheritance(name) {
                var result = 0;

                if (!style.hasItem(name)) {
                    result = (getParent().getStyleInheritance(name) == 2) ? 2 : 1;
                }

                return result;
            }

            function removeStyle(name) {
                style.removeItem(name);
            }

            this.isReadOnly          = isReadOnly;
            this.getName             = getName;
            this.getOptionHtml       = getOptionHtml;
            this.setStyle            = setStyle;
            this.getStyle            = getStyle;
            this.getStyleInheritance = getStyleInheritance;
            this.removeStyle         = removeStyle;
        }

        function Field(element) {
            this.setInheretanceClass = function(inheritance) {
                var cell = element.parentNode.parentNode;

                removeClass(cell, 'default');
                removeClass(cell, 'inherited');

                switch (inheritance) {
                    case 2:
                        addClass(cell, 'default');
                        break;
                    case 1:
                        addClass(cell, 'inherited');
                        break;
                }
            }

        }

        function SelectField(element, styleName) {
            this.baseClass = Field;
            this.baseClass(element);

            function setSelection(value) {
                for (var o = 0; o < element.options.length; o++) {
                    if (element.options[o].value == value) {
                        element.options[o].selected = true;
                    }
                }
            }

            this.onChange = function(namespace) {
                var value = element.options[element.selectedIndex].value;

                namespace.setStyle(styleName, value);

                this.setInheretanceClass(namespace.getStyleInheritance(styleName));

                if ((value == 'inherit') || namespace.isReadOnly()) {
                    setSelection(namespace.getStyle(styleName));
                }
            };

            this.update = function(namespace) {
                this.setInheretanceClass(namespace.getStyleInheritance(styleName));
                setSelection(namespace.getStyle(styleName));
                element.disabled = namespace.isReadOnly();
            };
        }

        function TextField(element, styleName) {
            this.baseClass = Field;
            this.baseClass(element);

            this.onChange = function(namespace) {
                /* Field-spacific validation. So far there is only one text field, so it can stay here */
                if (element.value.match(/(?:\d+\.?|\d*\.\d+)(?:%|em|px)|none/) == null) {
                    element.value = 'none';
                }

                namespace.setStyle(styleName, element.value);

                this.setInheretanceClass(namespace.getStyleInheritance(styleName));
            };

            this.update = function(namespace) {
                this.setInheretanceClass(namespace.getStyleInheritance(styleName));
                element.value = namespace.getStyle(styleName);
                element.disabled = namespace.isReadOnly();

                var button = $(element.id + '-inherit');

                if (button != null) {
                    button.disabled = namespace.isReadOnly();
                }
            };
        }


        var settings = new Hash(
            'refnote-id'           , 'numeric',
            'reference-base'       , 'super',
            'reference-font-weight', 'normal',
            'reference-font-style' , 'normal',
            'reference-format'     , 'right-parent',
            'notes-separator'      , '100%'
        );

        var namespaces = new Hash('', new DefaultNamespace());
        var current = namespaces.getItem('');

        function initialize() {
            addEvent($('select-namespaces'), 'change', onNamespaceChange);
            for (var styleName in settings.items) {
                addEvent($('field-' + styleName), 'change', onSettingChange);
            }
        }

        function onNamespaceChange(event) {
            var list = event.target;
            var name = '';

            if (list.selectedIndex != -1) {
                name = list.options[list.selectedIndex].value;

                if (!namespaces.hasItem(name)) {
                    name = '';
                }
            }

            current = namespaces.getItem(name);

            updateSettings();
        }

        function onSettingChange(event) {
            getField(event.target).onChange(current);
        }

        function getField(data) {
            var style, element, field;

            switch (typeof(data)) {
                case 'string':
                    style = data;
                    element = $('field-' + style);
                    break;

                case 'object':
                    element = data;
                    style = element.id.replace(/^field-/, '');
                    break;
            }

            switch (element.type) {
                case 'select-one':
                    field = new SelectField(element, style);
                    break;

                case 'text':
                    field = new TextField(element, style);
                    break;
            }

            return field;
        }

        function reload(settings) {
            namespaces = new Hash('', new DefaultNamespace());
            current = namespaces.getItem('');

            for (var name in settings) {
                if (name.match(/^:$|^:.+?:$/) != null) {
                    namespace = new Namespace(name);

                    for (var style in settings[name]) {
                        namespace.setStyle(style, settings[name][style]);
                    }

                    namespaces.setItem(name, namespace);

                    if (current.getName() == '') {
                        current = namespace;
                    }
                }
            }

            updateList();
            updateSettings();
        }

        function updateList() {
            var html = '';

            for (var name in namespaces.items) {
                html += namespaces.getItem(name).getOptionHtml();
            }

            var list = $('select-namespaces');

            list.innerHTML = html;

            for (var i = 0; i < list.options.length; i++) {
                if (list.options[i].value == current.getName()) {
                    list.options[i].selected = true;
                }
            }
        }

        function updateSettings() {
            for (var styleName in settings.items) {
                getField(styleName).update(current);
            }
        }

        return {
            initialize : initialize,
            reload     : reload
        };
    })();


    function initialize() {
        locale.initialize();
        namespaces.initialize();

        server.loadSettings();
    }

    function reloadSettings(settings) {
        namespaces.reload(settings['namespaces']);
    }

    function addClass(element, className) {
        var regexp = new RegExp('\\b' + className + '\\b', '');
        if (!element.className.match(regexp)) {
            element.className = (element.className + ' ' + className).replace(/^\s$/, '');
        }
    }

    function removeClass(element, className) {
        var regexp = new RegExp('\\b' + className + '\\b', '');
        element.className = element.className.replace(regexp, '').replace(/^\s|(\s)\s|\s$/g, '$1');
    }

    return {
        initialize : initialize
    };
})();


addInitEvent(function(){
    admin_refnotes.initialize();
});
