var refnotes_admin = (function () {
    var modified = false;

    function Hash() {
        /* Copy-pasted from http://www.mojavelinux.com/articles/javascript_hashes.html */
        this.length = 0;
        this.items = [];

        for (var i = 0; i < arguments.length; i += 2) {
            if (typeof(arguments[i + 1]) != 'undefined') {
                this.items[arguments[i]] = arguments[i + 1];
                this.length++;
            }
        }

        this.removeItem = function (key) {
            if (typeof(this.items[key]) != 'undefined') {
                this.length--;
                delete this.items[key];
            }
        }

        this.getItem = function (key) {
            return this.items[key];
        }

        this.setItem = function (key, value) {
            if (typeof(value) != 'undefined') {
                if (typeof(this.items[key]) == 'undefined') {
                    this.length++;
                }

                this.items[key] = value;
            }
        }

        this.hasItem = function (key) {
            return typeof(this.items[key]) != 'undefined';
        }
    }


    function NameHash(sentinel) {
        this.baseClass = Hash;
        this.baseClass('', sentinel);

        this.getItem = function (key) {
            return this.hasItem(key) ? this.items[key] : this.items[''];
        }

        this.clear = function () {
            for (var i in this.items) {
                if (i != '') {
                    this.length--;
                    delete this.items[i];
                }
            }
        }
    }


    function List(id) {
        var list = jQuery(id);

        function appendOption(value) {
            jQuery('<option>')
                .html(value)
                .val(value)
                .prop('sorting', value.replace(/:/g, '-').replace(/(-\w+)$/, '-$1'))
                .appendTo(list);
        }

        function sortOptions() {
            list.append(list.children().get().sort(function (a, b) {
                return a.sorting > b.sorting ? 1 : -1;
            }));
        }

        this.getSelectedValue = function () {
            return list.val();
        }

        this.insertValue = function (value) {
            appendOption(value);
            sortOptions();

            return list.children('[value="' + value + '"]').attr('selected', 'selected').val();
        }

        this.reload = function (values) {
            list.empty();

            for (var value in values.items) {
                if (value != '') {
                    appendOption(value);
                }
            }

            sortOptions();

            return list.children(':first').attr('selected', 'selected').val();
        }

        this.removeValue = function (value) {
            var option = list.children('[value="' + value + '"]');

            if (option.length == 1) {
                list.prop('selectedIndex', option.index() + (option.is(':last-child') ? -1 : 1));
                option.remove();
            }

            return list.val();
        }

        this.renameValue = function (oldValue, newValue) {
            if (list.children('[value="' + oldValue + '"]').remove().length == 1) {
                this.insertValue(newValue);
            }

            return list.val();
        }
    }


    var locale = (function () {
        var lang = new Hash();

        function initialize() {
            jQuery.each(jQuery('#refnotes-lang').html().split(/:eos:/), function (key, value) {
                var match = value.match(/^\s*(\w+) : (.+)/);
                if (match != null) {
                    lang.setItem(match[1], match[2]);
                }
            });
        }

        function getString(key) {
            var string = lang.hasItem(key) ? lang.getItem(key) : '';

            if ((string.length > 0) && (arguments.length > 1)) {
                for (var i = 1; i < arguments.length; i++) {
                    string = string.replace(new RegExp('\\{' + i + '\\}'), arguments[i]);
                }
            }

            return string;
        }

        return {
            initialize : initialize,
            getString  : getString
        }
    })();


    var server = (function () {
        var timer = null;
        var transaction = null;

        function sendRequest(request, data, success) {
            if (transaction == null) {
                transaction = request;

                jQuery.ajax({
                    cache : false,
                    data : data,
                    global : false,
                    success : success,
                    type : 'POST',
                    timeout : 10000,
                    url : DOKU_BASE + 'lib/exe/ajax.php',
                    beforeSend : function () {
                        setStatus('info', transaction);
                    },
                    error : function (xhr, status, message) {
                        setErorrStatus((status == 'parseerror') ? 'invalid_data' : transaction + '_failed', message);
                    },
                    dataFilter : function (data) {
                        var cookie = '{B27067E9-3DDA-4E31-9768-E66F23D18F4A}';
                        var match = data.match(new RegExp(cookie + '(.+?)' + cookie));

                        if ((match == null) || (match.length != 2)) {
                            throw 'Malformed response';
                        }

                        return match[1];
                    },
                    complete : function () {
                        transaction = null;
                    }
                });
            }
            else {
                setErorrStatus(request + '_failed', 'Server is busy');
            }
        }

        function loadSettings() {
            sendRequest('loading', {
                call : 'refnotes-admin',
                action : 'load-settings'
            }, function (data) {
                setSuccessStatus('loaded', 3000);
                reloadSettings(data);
            });
        }

        function saveSettings(settings) {
            sendRequest('saving', {
                call : 'refnotes-admin',
                action : 'save-settings',
                settings : JSON.stringify(settings)
            }, function (data) {
                if (data == 'saved') {
                    modified = false;

                    setSuccessStatus('saved', 10000);
                }
                else {
                    setErrorStatus('saving_failed', 'Server FS access error');
                }
            });
        }

        function setStatus(status, message) {
            window.clearTimeout(timer);

            if (message.match(/^\w+$/) != null) {
                message = locale.getString(message);
            }

            jQuery('#server-status')
                .removeClass()
                .addClass(status)
                .text(message);
        }

        function setErorrStatus(messageId, details) {
            setStatus('error', locale.getString(messageId, details));
        }

        function setSuccessStatus(messageId, timeout) {
            setStatus('success', messageId);

            timer = window.setTimeout(function () {
                setStatus('cleared', 'status');
            }, timeout);
        }

        return {
            loadSettings : loadSettings,
            saveSettings : saveSettings
        }
    })();


    var general = (function () {
        var fields   = new Hash();
        var defaults = new Hash(
            'replace-footnotes', false,
            'reference-db-enable', false,
            'reference-db-namespace', ':refnotes:'
        );

        function Field(settingName) {
            this.element = $('field-' + settingName);

            this.updateDefault = function (value) {
                var cell = this.element.parentNode.parentNode;

                if (value == defaults.getItem(settingName)) {
                    addClass(cell, 'default');
                }
                else {
                    removeClass(cell, 'default');
                }
            }

            this.enable = function (enable) {
                this.element.disabled = !enable;
            }
        }

        function CheckField(settingName) {
            this.baseClass = Field;
            this.baseClass(settingName);

            var check = this.element;
            var self  = this;

            addEvent(check, 'change', function () {
                self.onChange();
            });

            this.onChange = function () {
                this.updateDefault(check.checked);

                modified = true;
            }

            this.setValue = function (value) {
                check.checked = value;
                this.updateDefault(check.checked);
            }

            this.getValue = function () {
                return check.checked;
            }

            this.setValue(defaults.getItem(settingName));
            this.enable(false);
        }

        function TextField(settingName) {
            this.baseClass = Field;
            this.baseClass(settingName);

            var edit = this.element;
            var self = this;

            addEvent(edit, 'change', function () {
                self.onChange();
            });

            this.onChange = function () {
                this.updateDefault(edit.value);

                modified = true;
            }

            this.setValue = function (value) {
                edit.value = value;
                this.updateDefault(edit.value);
            }

            this.getValue = function () {
                return edit.value;
            }

            this.setValue(defaults.getItem(settingName));
            this.enable(false);
        }

        function initialize() {
            addField('replace-footnotes', CheckField);
            addField('reference-db-enable', CheckField);
            addField('reference-db-namespace', TextField);
        }

        function addField(settingName, field) {
            fields.setItem(settingName, new field(settingName));
        }

        function reload(settings) {
            for (var name in settings) {
                if (fields.hasItem(name)) {
                    fields.getItem(name).setValue(settings[name]);
                }
            }

            for (name in fields.items) {
                fields.getItem(name).enable(true);
            }
        }

        function getSettings() {
            var settings = {};

            for (var name in fields.items) {
                settings[name] = fields.getItem(name).getValue();
            }

            return settings;
        }

        return {
            initialize  : initialize,
            reload      : reload,
            getSettings : getSettings
        }
    })();


    var namespaces = (function () {
        var list       = null;
        var fields     = [];
        var namespaces = new NameHash(new DefaultNamespace());
        var current    = namespaces.getItem('');
        var defaults   = new Hash(
            'refnote-id'           , 'numeric',
            'reference-base'       , 'super',
            'reference-font-weight', 'normal',
            'reference-font-style' , 'normal',
            'reference-format'     , 'right-parent',
            'reference-render'     , 'basic',
            'multi-ref-id'         , 'ref-counter',
            'note-preview'         , 'popup',
            'notes-separator'      , '100%',
            'note-text-align'      , 'justify',
            'note-font-size'       , 'normal',
            'note-render'          , 'basic',
            'note-id-base'         , 'super',
            'note-id-font-weight'  , 'normal',
            'note-id-font-style'   , 'normal',
            'note-id-format'       , 'right-parent',
            'back-ref-caret'       , 'none',
            'back-ref-base'        , 'super',
            'back-ref-font-weight' , 'bold',
            'back-ref-font-style'  , 'normal',
            'back-ref-format'      , 'note-id',
            'back-ref-separator'   , 'comma',
            'scoping'              , 'reset'
        );

        function DefaultNamespace() {
            this.isReadOnly = function () {
                return true;
            }

            this.setName = function (newName) {
            }

            this.getName = function () {
                return '';
            }

            this.setStyle = function (name, value) {
            }

            this.getStyle = function (name) {
                return defaults.getItem(name);
            }

            this.getStyleInheritance = function (name) {
                return 'default';
            }

            this.getSettings = function () {
                return {};
            }
        }

        function Namespace(name, data) {
            var styles = new Hash();

            if (typeof(data) != 'undefined') {
                for (var s in data) {
                    styles.setItem(s, data[s]);
                }
            }

            function getParent() {
                var parent = name.replace(/\w*:$/, '');

                while (!namespaces.hasItem(parent)) {
                    parent = parent.replace(/\w*:$/, '');
                }

                return namespaces.getItem(parent);
            }

            this.isReadOnly = function () {
                return false;
            }

            this.setName = function (newName) {
                name = newName;
            }

            this.getName = function () {
                return name;
            }

            this.setStyle = function (name, value) {
                if (value == 'inherit') {
                    styles.removeItem(name);
                }
                else {
                    styles.setItem(name, value);
                }
            }

            this.getStyle = function (name) {
                var result;

                if (styles.hasItem(name)) {
                    result = styles.getItem(name);
                }
                else {
                    result = getParent().getStyle(name);
                }

                return result;
            }

            this.getStyleInheritance = function (name) {
                var result = '';

                if (!styles.hasItem(name)) {
                    result = getParent().getStyleInheritance(name) || 'inherited';
                }

                return result;
            }

            this.getSettings = function () {
                var settings = {};

                for (var name in styles.items) {
                    settings[name] = styles.getItem(name);
                }

                return settings;
            }
        }

        function Field(styleName) {
            this.element = $('field-' + styleName);

            this.updateInheretance = function () {
                var cell = this.element.parentNode.parentNode;

                removeClass(cell, 'default');
                removeClass(cell, 'inherited');

                addClass(cell, current.getStyleInheritance(styleName));
            }
        }

        function SelectField(styleName) {
            this.baseClass = Field;
            this.baseClass(styleName);

            var combo = this.element;
            var self  = this;

            addEvent(combo, 'change', function () {
                self.onChange();
            });

            function setSelection(value) {
                for (var o = 0; o < combo.options.length; o++) {
                    if (combo.options[o].value == value) {
                        combo.options[o].selected = true;
                    }
                }
            }

            this.onChange = function () {
                var value = combo.options[combo.selectedIndex].value;

                current.setStyle(styleName, value);

                this.updateInheretance();

                if ((value == 'inherit') || current.isReadOnly()) {
                    setSelection(current.getStyle(styleName));
                }

                modified = true;
            }

            this.update = function () {
                this.updateInheretance();
                setSelection(current.getStyle(styleName));
                combo.disabled = current.isReadOnly();
            }
        }

        function TextField(styleName, validate) {
            this.baseClass = Field;
            this.baseClass(styleName);

            var edit   = this.element;
            var button = $(this.element.id + '-inherit');
            var self   = this;

            addEvent(edit, 'change', function () {
                self.setValue(validate(edit.value));
            });

            addEvent(button, 'click', function () {
                self.setValue('inherit');
            });

            this.setValue = function (value) {
                current.setStyle(styleName, value);

                this.updateInheretance();

                if ((edit.value != value) || (value == 'inherit') || current.isReadOnly()) {
                    edit.value = current.getStyle(styleName);
                }

                modified = true;
            }

            this.update = function () {
                this.updateInheretance();

                edit.value      = current.getStyle(styleName);
                edit.disabled   = current.isReadOnly();
                button.disabled = current.isReadOnly();
            }
        }

        function initialize() {
            fields.push(new SelectField('refnote-id'));
            fields.push(new SelectField('reference-base'));
            fields.push(new SelectField('reference-font-weight'));
            fields.push(new SelectField('reference-font-style'));
            fields.push(new SelectField('reference-format'));
            fields.push(new SelectField('reference-render'));
            fields.push(new SelectField('multi-ref-id'));
            fields.push(new SelectField('note-preview'));
            fields.push(new TextField('notes-separator', function (value) {
                if (value.match(/(?:\d+\.?|\d*\.\d+)(?:%|em|px)|none/) == null) {
                    value = 'none';
                }
                return value;
            }));
            fields.push(new SelectField('note-text-align'));
            fields.push(new SelectField('note-font-size'));
            fields.push(new SelectField('note-render'));
            fields.push(new SelectField('note-id-base'));
            fields.push(new SelectField('note-id-font-weight'));
            fields.push(new SelectField('note-id-font-style'));
            fields.push(new SelectField('note-id-format'));
            fields.push(new SelectField('back-ref-caret'));
            fields.push(new SelectField('back-ref-base'));
            fields.push(new SelectField('back-ref-font-weight'));
            fields.push(new SelectField('back-ref-font-style'));
            fields.push(new SelectField('back-ref-format'));
            fields.push(new SelectField('back-ref-separator'));
            fields.push(new SelectField('scoping'));

            list = new List('#select-namespaces');

            addEvent($('select-namespaces'), 'change', onNamespaceChange);
            addEvent($('add-namespaces'), 'click', onAddNamespace);
            addEvent($('rename-namespaces'), 'click', onRenameNamespace);
            addEvent($('delete-namespaces'), 'click', onDeleteNamespace);

            $('name-namespaces').disabled   = true;
            $('add-namespaces').disabled    = true;
            $('rename-namespaces').disabled = true;
            $('delete-namespaces').disabled = true;

            updateFields();
        }

        function onNamespaceChange(event) {
            setCurrent(list.getSelectedValue());
        }

        function onAddNamespace(event) {
            try {
                var name = validateName($('name-namespaces').value, 'ns', namespaces);

                namespaces.setItem(name, new Namespace(name));

                setCurrent(list.insertValue(name));

                modified = true;
            }
            catch (error) {
                alert(error);
            }
        }

        function onRenameNamespace(event) {
            try {
                var newName = validateName($('name-namespaces').value, 'ns', namespaces);
                var oldName = current.getName();

                current.setName(newName);

                namespaces.removeItem(oldName);
                namespaces.setItem(newName, current);

                setCurrent(list.renameValue(oldName, newName));

                modified = true;
            }
            catch (error) {
                alert(error);
            }
        }

        function onDeleteNamespace(event) {
            if (confirm(locale.getString('delete_ns', current.getName()))) {
                namespaces.removeItem(current.getName());

                setCurrent(list.removeValue(current.getName()));

                modified = true;
            }
        }

        function reload(settings) {
            namespaces.clear();

            for (var name in settings) {
                if (name.match(/^:$|^:.+?:$/) != null) {
                    namespaces.setItem(name, new Namespace(name, settings[name]));
                }
            }

            $('name-namespaces').disabled = false;
            $('add-namespaces').disabled  = false;

            setCurrent(list.reload(namespaces));
        }

        function setCurrent(name) {
            current = namespaces.getItem(name);

            updateFields();
        }

        function updateFields() {
            $('name-namespaces').value      = current.getName();
            $('rename-namespaces').disabled = current.isReadOnly();
            $('delete-namespaces').disabled = current.isReadOnly();

            for (var i = 0; i < fields.length; i++) {
                fields[i].update();
            }
        }

        function getSettings() {
            var settings = {};

            for (var name in namespaces.items) {
                settings[name] = namespaces.getItem(name).getSettings();
            }

            return settings;
        }

        return {
            initialize  : initialize,
            reload      : reload,
            getSettings : getSettings
        }
    })();


    var notes = (function () {
        var list    = null;
        var notes   = new NameHash(new EmptyNote());
        var current = notes.getItem('');

        function EmptyNote() {
            this.isReadOnly = function () {
                return true;
            }

            this.setName = function (newName) {
            }

            this.getName = function () {
                return '';
            }

            this.setText = function (text) {
            }

            this.getText = function () {
                return '';
            }

            this.setInline = function (inline) {
            }

            this.isInline = function () {
                return false;
            }

            this.getSettings = function () {
                return {};
            }
        }

        function Note(name, data) {
            this.text   = '';
            this.inline = false;

            if (typeof(data) != 'undefined') {
                this.text   = data.text;
                this.inline = data.inline;
            }

            this.isReadOnly = function () {
                return false;
            }

            this.setName = function (newName) {
                name = newName;
            }

            this.getName = function () {
                return name;
            }

            this.setText = function (text) {
                this.text = text;
            }

            this.getText = function () {
                return this.text;
            }

            this.setInline = function (inline) {
                this.inline = inline;
            }

            this.isInline = function () {
                return this.inline;
            }

            this.getSettings = function () {
                return {
                    text   : this.text,
                    inline : this.inline
                }
            }
        }

        function initialize() {
            list = new List('#select-notes');

            addEvent($('select-notes'), 'change', onNoteChange);
            addEvent($('add-notes'), 'click', onAddNote);
            addEvent($('rename-notes'), 'click', onRenameNote);
            addEvent($('delete-notes'), 'click', onDeleteNote);

            addEvent($('field-note-text'), 'change', onTextChange);
            addEvent($('field-inline'), 'change', onInlineChange);

            $('name-notes').disabled   = true;
            $('add-notes').disabled    = true;
            $('rename-notes').disabled = true;
            $('delete-notes').disabled = true;

            updateFields();
        }

        function onNoteChange(event) {
            setCurrent(list.getSelectedValue());
        }

        function onAddNote(event) {
            try {
                var name = validateName($('name-notes').value, 'note', notes);

                notes.setItem(name, new Note(name));

                setCurrent(list.insertValue(name));

                modified = true;
            }
            catch (error) {
                alert(error);
            }
        }

        function onRenameNote(event) {
            try {
                var newName = validateName($('name-notes').value, 'note', notes);
                var oldName = current.getName();

                current.setName(newName);

                notes.removeItem(oldName);
                notes.setItem(newName, current);

                setCurrent(list.renameValue(oldName, newName));

                modified = true;
            }
            catch (error) {
                alert(error);
            }
        }

        function onDeleteNote(event) {
            if (confirm(locale.getString('delete_note', current.getName()))) {
                notes.removeItem(current.getName());

                setCurrent(list.removeValue(current.getName()));

                modified = true;
            }
        }

        function onTextChange(event) {
            current.setText(event.target.value);

            modified = true;
        }

        function onInlineChange(event) {
            current.setInline(event.target.checked);

            modified = true;
        }

        function reload(settings) {
            notes.clear();

            for (var name in settings) {
                if (name.match(/^:.+?\w$/) != null) {
                    notes.setItem(name, new Note(name, settings[name]));
                }
            }

            $('name-notes').disabled = false;
            $('add-notes').disabled  = false;

            setCurrent(list.reload(notes));
        }

        function setCurrent(name) {
            current = notes.getItem(name);

            updateFields();
        }

        function updateFields() {
            $('name-notes').value      = current.getName();
            $('rename-notes').disabled = current.isReadOnly();
            $('delete-notes').disabled = current.isReadOnly();

            var field = $('field-note-text');

            field.value    = current.getText();
            field.disabled = current.isReadOnly();

            field = $('field-inline');

            field.checked  = current.isInline();
            field.disabled = current.isReadOnly();
        }

        function getSettings() {
            var settings = {};

            for (var name in notes.items) {
                settings[name] = notes.getItem(name).getSettings();
            }

            return settings;
        }

        return {
            initialize  : initialize,
            reload      : reload,
            getSettings : getSettings
        }
    })();


    function initialize() {
        locale.initialize();
        general.initialize();
        namespaces.initialize();
        notes.initialize();

        addEvent($('save-config'), 'click', function (event) {
            saveSettings();
        });

        window.onbeforeunload = onBeforeUnload;

        $('server-status').style.display = 'block';

        server.loadSettings();
    }

    function reloadSettings(settings) {
        general.reload(settings.general);
        namespaces.reload(settings.namespaces);
        notes.reload(settings.notes);
    }

    function saveSettings() {
        var settings = {};

        settings.general    = general.getSettings();
        settings.namespaces = namespaces.getSettings();
        settings.notes      = notes.getSettings();

        server.saveSettings(settings);

        scroll(0, 0);
    }

    function onBeforeUnload(event) {
        if (modified) {
            var message = locale.getString('unsaved');

            (event || window.event).returnValue = message;

            return message;
        }
    }

    function validateName(name, type, existing) {
        var names = name.split(':');

        name = (type == 'ns') ? ':' : '';

        for (var i = 0; i < names.length; i++) {
            if (names[i] != '') {
                /* ECMA regexp doesn't support POSIX character classes, so [a-zA-Z] is used instead of [[:alpha:]] */
                if (names[i].match(/^[a-zA-Z]\w*$/) == null) {
                    name = '';
                    break;
                }

                name += (type == 'ns') ? names[i] + ':' : ':' + names[i];
            }
        }

        if (name == '') {
            throw locale.getString('invalid_' + type + '_name');
        }

        if (existing.hasItem(name)) {
            throw locale.getString(type + '_name_exists', name);
        }

        return name;
    }

    function addClass(element, className) {
        if (className != '') {
            var regexp = new RegExp('\\b' + className + '\\b');
            if (!element.className.match(regexp)) {
                element.className = (element.className + ' ' + className).replace(/^\s/, '');
            }
        }
    }

    function removeClass(element, className) {
        var regexp = new RegExp('\\b' + className + '\\b');
        element.className = element.className.replace(regexp, '').replace(/^\s|(\s)\s|\s$/g, '$1');
    }

    return {
        initialize : initialize
    }
})();


jQuery(function () {
    if (jQuery('#refnotes-config').length != 0) {
        refnotes_admin.initialize();
    }
});
