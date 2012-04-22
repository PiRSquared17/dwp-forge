<?php

/**
 * Plugin RefNotes: Reference
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <spambox03@mail.ru>
 */

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_reference_mock {

    private $note;

    /**
     * Constructor
     */
    public function __construct($note) {
        $this->note = $note;
    }

    /**
     *
     */
    public function getNote() {
        return $this->note;
    }

    /**
     *
     */
    public function render() {
        return '';
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_parser_reference extends refnotes_refnote {

    private $startOfText;

    /**
     * Constructor
     */
    public function __construct($name, $data, $startOfText) {
        list($namespace, $name) = refnotes_parseName($name);

        if (preg_match('/(?:@@FNT|#)(\d+)/', $name, $match) == 1) {
            $name = intval($match[1]);
        }

        parent::__construct(array('ns' => $namespace, 'name' => $name));

        $this->startOfText = $startOfText;

        if ($data != '') {
            $this->parseStructuredData($data);
        }
    }

    /**
     *
     */
    private function parseStructuredData($syntax) {
        preg_match_all('/([-\w]+)\s*[:=]\s*(.+?)\s*?(:?[\n|;]|$)/', $syntax, $match, PREG_SET_ORDER);

        foreach ($match as $m) {
            $this->data[$m[1]] = $m[2];
        }
    }

    /**
     *
     */
    public function isNamed() {
        return !is_int($this->attributes['name']) && ($this->attributes['name'] != '');
    }

    /**
     *
     */
    public function getNamespace() {
        return $this->attributes['ns'];
    }

    /**
     *
     */
    public function isTextDefined($endOfText) {
        return $endOfText > $this->startOfText;
    }

    /**
     *
     */
    public function hasData() {
        return !empty($this->data);
    }

    /**
     *
     */
    public function updateAttributes($attributes) {
        static $key = array('inline', 'source');

        foreach ($key as $k) {
            if (array_key_exists($k, $attributes)) {
                $this->attributes[$k] = $attributes[$k];
            }
        }
    }

    /**
     *
     */
    public function updateData($data) {
        $this->data = array_merge($data, $this->data);
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_reference extends refnotes_refnote {

    protected $inline;
    protected $hidden;
    protected $note;
    protected $id;

    /**
     * Constructor
     */
    public function __construct($note, $attributes, $data) {
        parent::__construct($attributes, $data);

        $this->inline = isset($this->attributes['inline']) ? $this->attributes['inline'] : false;
        $this->hidden = isset($this->attributes['hidden']) ? $this->attributes['hidden'] : false;
        $this->note = $note;
        $this->id = -1;

        if ($this->isBackReferenced()) {
            $this->id = $this->note->getScope()->getReferenceId();
        }
    }

    /**
     *
     */
    public function getId() {
        return $this->id;
    }

    /**
     *
     */
    public function getNote() {
        return $this->note;
    }

    /**
     *
     */
    public function isInline() {
        return $this->inline;
    }

    /**
     *
     */
    public function isBackReferenced() {
        return !$this->inline && !$this->hidden;
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_renderer_reference extends refnotes_reference {

    /**
     *
     */
    public function getAnchorName() {
        $result = 'refnotes';
        $result .= $this->note->getScope()->getName();
        $result .= ':ref' . $this->id;

        return $result;
    }

    /**
     *
     */
    public function render() {
        $html = '';

        if (!$this->hidden) {
            if ($this->inline) {
                $html = '<sup>' . $this->note->getText() . '</sup>';
            }
            else {
                $html = $this->note->getScope()->getRenderer()->renderReference($this);
            }
        }

        return $html;
    }
}
