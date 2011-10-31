<?php

/**
 * Plugin RefNotes: Notes collection
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <spambox03@mail.ru>
 */

/* Must be run within Dokuwiki */
if (!defined('DOKU_INC') || !defined('DOKU_PLUGIN')) die();

require_once(DOKU_INC . 'inc/plugin.php');
require_once(DOKU_PLUGIN . 'refnotes/info.php');
require_once(DOKU_PLUGIN . 'refnotes/locale.php');
require_once(DOKU_PLUGIN . 'refnotes/config.php');
require_once(DOKU_PLUGIN . 'refnotes/namespace.php');

////////////////////////////////////////////////////////////////////////////////////////////////////
class helper_plugin_refnotes extends DokuWiki_Plugin {

    private static $instance = NULL;

    private $namespaceStyle;
    private $namespace;

    /**
     *
     */
    public static function getInstance() {
        if (self::$instance == NULL) {
            self::$instance = plugin_load('helper', 'refnotes');
            if (self::$instance == NULL) {
                throw new Exception('Helper plugin "refnotes" is not available or invalid.');
            }
        }

        return self::$instance;
    }

    /**
     * Constructor
     */
    public function __construct() {
        refnotes_localization::initialize($this);

        $this->namespaceStyle = refnotes_configuration::load('namespaces');
        $this->namespace = array();
    }

    /**
     * Return some info
     */
    public function getInfo() {
        return refnotes_getInfo('notes collection');
    }

    /**
     * Don't publish any methods (it's not a public helper)
     */
    public function getMethods() {
        return array();
    }

    /**
     * Adds a reference to the notes array
     */
    public function addReference($info) {
        $reference = new refnotes_reference($info);

        $this->getNamespace($reference->getNamespace())->addReference($reference);

        return $reference;
    }

    /**
     *
     */
    public function styleNamespace($namespaceName, $style) {
        $namespace = $this->getNamespace($namespaceName);

        if (array_key_exists('inherit', $style)) {
            $source = $this->getNamespace($style['inherit']);
            $namespace->inheritStyle($source);
        }

        $namespace->style($style);
    }

    /**
     *
     */
    public function renderNotes($namespaceName, $limit = '') {
        $html = '';
        if ($namespaceName == '*') {
            foreach ($this->namespace as $namespace) {
                $html .= $namespace->renderNotes();
            }
        }
        else {
            $namespace = $this->findNamespace($namespaceName);
            if ($namespace != NULL) {
                $html = $namespace->renderNotes($limit);
            }
        }

        return $html;
    }

    /**
     * Returns a namespace given it's name. The namespace is created if it doesn't exist yet.
     */
    public function getNamespace($name) {
        $result = $this->findNamespace($name);

        if ($result == NULL) {
            $result = $this->createNamespace($name);
        }

        return $result;
    }

    /**
     * Finds a namespace given it's name
     */
    private function findNamespace($name) {
        $result = NULL;
        if (array_key_exists($name, $this->namespace)) {
            $result = $this->namespace[$name];
        }

        return $result;
    }

    /**
     *
     */
    private function createNamespace($name) {
        if ($name != ':') {
            $parentName = refnotes_getParentNamespace($name);
            $parent = $this->getNamespace($parentName);
            $this->namespace[$name] = new refnotes_namespace($name, $parent);
        }
        else {
            $this->namespace[$name] = new refnotes_namespace($name);
        }

        if (array_key_exists($name, $this->namespaceStyle)) {
            $this->namespace[$name]->style($this->namespaceStyle[$name]);
        }

        return $this->namespace[$name];
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_namespace {

    private $name;
    private $style;
    private $renderer;
    private $scope;
    private $newScope;

    /**
     * Constructor
     */
    public function __construct($name, $parent = NULL) {
        $this->name = $name;
        $this->style = array();
        $this->renderer = NULL;
        $this->scope = array();
        $this->newScope = true;

        if ($parent != NULL) {
            $this->style = $parent->style;
        }
    }

    /**
     *
     */
    public function getName() {
        return $this->name;
    }

    /**
     *
     */
    public function style($style) {
        foreach ($style as $property => $value) {
            $this->style[$property] = $value;
        }
    }

    /**
     *
     */
    public function inheritStyle($source) {
        $this->style = $source->style;
    }

    /**
     *
     */
    public function getStyle($property) {
        $result = '';

        if (array_key_exists($property, $this->style)) {
            $result = $this->style[$property];
        }

        return $result;
    }

    /**
     *
     */
    public function getRenderer() {
        if ($this->renderer == NULL) {
            switch ($this->getStyle('data-presentation')) {
                case 'harvard':
                    $this->renderer = new refnotes_harvard_renderer($this);
                    break;

                default:
                    $this->renderer = new refnotes_basic_renderer($this);
                    break;
            }
        }

        return $this->renderer;
    }

    /**
     * Adds a reference to the current scope
     */
    public function addReference($reference) {
        if ($this->newScope) {
            $id = count($this->scope) + 1;
            $this->scope[] = new refnotes_scope($this, $id);
            $this->newScope = false;
        }

        $reference->joinScope(end($this->scope));
    }

    /**
     *
     */
    public function renderNotes($limit = '') {
        $this->resetScope();
        $html = '';
        if (count($this->scope) > 0) {
            $scope = end($this->scope);
            $limit = $this->getRenderLimit($limit, $scope);
            $html = $scope->renderNotes($limit);
        }

        return $html;
    }

    /**
     *
     */
    private function resetScope() {
        switch ($this->getStyle('scoping')) {
            case 'single':
                break;

            default:
                $this->newScope = true;
                break;
        }
    }

    /**
     *
     */
    private function getRenderLimit($limit, $scope) {
        if (preg_match('/(\/?)(\d+)/', $limit, $match) == 1) {
            if ($match[1] != '') {
                $devider = intval($match[2]);
                $result = ceil($scope->getRenderableCount() / $devider);
            }
            else {
                $result = intval($match[2]);
            }
        }
        else {
            $result = 0;
        }

        return $result;
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_scope {

    private $namespace;
    private $id;
    private $note;
    private $notes;
    private $references;

    /**
     * Constructor
     */
    public function __construct($namespace, $id) {
        $this->namespace = $namespace;
        $this->id = $id;
        $this->note = array();
        $this->notes = 0;
        $this->references = 0;
    }

    /**
     *
     */
    public function getName() {
        return $this->namespace->getName() . $this->id;
    }

    /**
     *
     */
    public function getRenderer() {
        return $this->namespace->getRenderer();
    }

    /**
     *
     */
    public function getNoteId() {
        return ++$this->notes;
    }

    /**
     *
     */
    public function getReferenceId() {
        return ++$this->references;
    }

    /**
     * Returns the number of renderable notes in the scope
     */
    public function getRenderableCount() {
        $result = 0;
        foreach ($this->note as $note) {
            if ($note->isRenderable()) {
                ++$result;
            }
        }

        return $result;
    }

    /**
     *
     */
    public function addNote($note) {
        $this->note[] = $note;
    }

    /**
     *
     */
    public function renderNotes($limit) {
        $html = '';
        $count = 0;
        foreach ($this->note as $note) {
            if ($note->isRenderable()) {
                $html .= $note->render();
                if (($limit != 0) && (++$count == $limit)) {
                    break;
                }
            }
        }

        if ($html != '') {
            $open = $this->getRenderer()->renderNotesSeparator() . '<div class="notes">' . DOKU_LF;
            $close = '</div>' . DOKU_LF;
            $html = $open . $html . $close;
        }

        return $html;
    }

    /**
     * Finds a note given it's name or id
     */
    public function findNote($name) {
        $result = NULL;

        if ($name != '') {
            $getter = is_int($name) ? 'getId' : 'getName';

            foreach ($this->note as $note) {
                if ($note->$getter() == $name) {
                    $result = $note;
                    break;
                }
            }
        }

        return $result;
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_reference {

    private $namespace;
    private $name;
    private $inline;
    private $hidden;
    private $data;
    private $scope;
    private $note;
    private $id;

    /**
     * Constructor
     */
    public function __construct($info) {
        $this->namespace = $info['ns'];
        $this->name = $info['name'];
        $this->inline = isset($info['inline']) ? $info['inline'] : false;
        $this->hidden = isset($info['hidden']) ? $info['hidden'] : false;
        $this->data = $info;
        $this->scope = NULL;
        $this->note = NULL;
        $this->id = 0;

        if (preg_match('/(?:@@FNT|#)(\d+)/', $this->name, $match) == 1) {
            $this->name = intval($match[1]);
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
    public function getNamespace() {
        return $this->namespace;
    }

    /**
     *
     */
    public function getAnchorName() {
        $result = 'refnotes';
        $result .= $this->scope->getName();
        $result .= ':ref' . $this->id;

        return $result;
    }

    /**
     *
     */
    public function getNote() {
        $result = $this->note;

        if ($result == NULL) {
            $result = new refnotes_note_mock();
        }

        return $result;
    }

    /**
     *
     */
    public function joinScope($scope) {
        $note = $scope->findNote($this->name);

        if (($note == NULL) && !is_int($this->name)) {
            $note = new refnotes_note($scope, $this->name, $this->inline);

            $scope->addNote($note);
        }

        if (($note != NULL) && !$this->hidden && !$this->inline) {
            $this->id = $scope->getReferenceId();

            $note->addReference($this);
        }

        $this->scope = $scope;
        $this->note = $note;
    }

    /**
     *
     */
    public function render() {
        $html = '';

        if (($this->note != NULL) && !$this->hidden) {
            if ($this->inline) {
                $html = '<sup>' . $this->note->getText() . '</sup>';
            }
            else {
                $html = $this->scope->getRenderer()->renderReference($this);
            }
        }

        return $html;
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_note_mock {

    /**
     *
     */
    public function setText($text) {
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_note {

    private $scope;
    private $id;
    private $name;
    private $inline;
    private $reference;
    private $text;
    private $rendered;

    /**
     * Constructor
     */
    public function __construct($scope, $name, $inline) {
        $this->scope = $scope;
        $this->id = $inline ? 0 : $scope->getNoteId();

        if ($name != '') {
            $this->name = $name;
        }
        else {
            $this->name = '#' . $id;
        }

        $this->inline = $inline;
        $this->reference = array();
        $this->text = '';
        $this->rendered = false;
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
    public function getName() {
        return $this->name;
    }

    /**
     *
     */
    public function getAnchorName() {
        $result = 'refnotes';
        $result .= $this->scope->getName();
        $result .= ':note' . $this->id;

        return $result;
    }

    /**
     *
     */
    public function addReference($reference) {
        $this->reference[] = $reference;
    }

    /**
     *
     */
    public function setText($text) {
        if (($this->text == '') || !$this->inline) {
            $this->text = $text;
        }
    }

    /**
     *
     */
    public function getText() {
        return $this->text;
    }

    /**
     * Checks if the note should be rendered
     */
    public function isRenderable() {
        return !$this->rendered && (count($this->reference) > 0) && ($this->text != '');
    }

    /**
     *
     */
    public function render() {
        $html = $this->scope->getRenderer()->renderNote($this, $this->reference);

        $this->rendered = true;

        return $html;
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_basic_renderer {

    private $namespace;

    /**
     * Constructor
     */
    public function __construct($namespace) {
        $this->namespace = $namespace;
    }

    /**
     *
     */
    public function renderNoteText($data) {
        if (array_key_exists('note-text', $data)) {
            $text = $data['note-text'];
        }
        elseif (array_key_exists('title', $data)) {
            $text = $data['title'];
        }
        else {
            $text = '';
            foreach($data as $value) {
                if (strlen($text) < strlen($value)) {
                    $text = $value;
                }
            }
        }

        if (array_key_exists('url', $data)) {
            $text = '[[' . $data['url'] . '|' . $text . ']]';
        }

        return $text;
    }

    /**
     *
     */
    public function renderReference($reference) {
        $html = '';

        $noteName = $reference->getNote()->getAnchorName();
        $referenceName = $reference->getAnchorName();
        $class = $this->renderReferenceClass();

        list($baseOpen, $baseClose) = $this->renderReferenceBase();
        list($fontOpen, $fontClose) = $this->renderReferenceFont();
        list($formatOpen, $formatClose) = $this->renderReferenceFormat();

        $html = $baseOpen . $fontOpen;
        $html .= '<a href="#' . $noteName . '" name="' . $referenceName . '" class="' . $class . '">';
        $html .= $formatOpen . $this->renderReferenceId($reference) . $formatClose;
        $html .= '</a>';
        $html .= $fontClose . $baseClose;

        return $html;
    }

    /**
     *
     */
    public function renderNotesSeparator() {
        $html = '';
        $style = $this->getStyle('notes-separator');
        if ($style != 'none') {
            if ($style != '') {
                $style = ' style="width: '. $style . '"';
            }
            $html = '<hr' . $style . '>' . DOKU_LF;
        }

        return $html;
    }

    /**
     *
     */
    public function renderNote($note, $reference) {
        $html = '<div class="' . $this->renderNoteClass() . '">' . DOKU_LF;
        $html .= $this->renderBackReferences($note, $reference);
        $html .= '<span id="' . $note->getAnchorName() . ':text">' . DOKU_LF;
        $html .= $note->getText() . DOKU_LF;
        $html .= '</span></div>' . DOKU_LF;

        $this->rendered = true;

        return $html;
    }

    /**
     *
     */
    protected function renderBackReferences($note, $reference) {
        $references = count($reference);
        $singleReference = ($references == 1);
        $nameAttribute = ' name="' . $note->getAnchorName() .'"';
        $backRefFormat = $this->getStyle('back-ref-format');
        $backRefCaret = '';
        list($formatOpen, $formatClose) = $this->renderNoteIdFormat();

        if (($backRefFormat != 'note') && ($backRefFormat != '')) {
            list($baseOpen, $baseClose) = $this->renderNoteIdBase();
            list($fontOpen, $fontClose) = $this->renderNoteIdFont();

            $html .= $baseOpen . $fontOpen;
            $html .= '<a' . $nameAttribute .' class="nolink">';
            $html .= $formatOpen . $this->renderNoteId($note) . $formatClose;
            $html .= '</a>';
            $html .= $fontClose . $baseClose . DOKU_LF;

            $nameAttribute = '';
            $formatOpen = '';
            $formatClose = '';
            $backRefCaret = $this->renderBackRefCaret($singleReference);
        }

        if ($backRefFormat != 'none') {
            $separator = $this->renderBackRefSeparator();
            list($baseOpen, $baseClose) = $this->renderBackRefBase();
            list($fontOpen, $fontClose) = $this->renderBackRefFont();

            $html .= $baseOpen . $backRefCaret;

            for ($r = 0; $r < $references; $r++) {
                $referenceName = $reference[$r]->getAnchorName();

                if ($r > 0) {
                    $html .= $separator . DOKU_LF;
                }

                $html .= $fontOpen;
                $html .= '<a href="#' . $referenceName . '"' . $nameAttribute .' class="backref">';
                $html .= $formatOpen . $this->renderBackRefId($reference[$r], $r, $singleReference) . $formatClose;
                $html .= '</a>';
                $html .= $fontClose;

                $nameAttribute = '';
            }

            $html .= $baseClose . DOKU_LF;
        }

        return $html;
    }

    /**
     *
     */
    protected function getStyle($property) {
        return $this->namespace->getStyle($property);
    }

    /**
     *
     */
    protected function renderReferenceClass() {
        switch ($this->getStyle('note-preview')) {
            case 'tooltip':
                $result = 'refnotes-ref note-tooltip';
                break;

            case 'none':
                $result = 'refnotes-ref';
                break;

            default:
                $result = 'refnotes-ref note-popup';
                break;
        }

        return $result;
    }

    /**
     *
     */
    protected function renderReferenceBase() {
        return $this->renderBase($this->getStyle('reference-base'));
    }

    /**
     *
     */
    protected function renderReferenceFont() {
        return $this->renderFont('reference-font-weight', 'normal', 'reference-font-style');
    }

    /**
     *
     */
    protected function renderReferenceFormat() {
        return $this->renderFormat($this->getStyle('reference-format'));
    }

    /**
     *
     */
    protected function renderReferenceId($reference) {
        $idStyle = $this->getStyle('refnote-id');
        if ($idStyle == 'name') {
            $html = $reference->getNote()->getName();
        }
        else {
            switch ($this->getStyle('multi-ref-id')) {
                case 'note':
                    $id = $reference->getNote()->getId();
                    break;

                default:
                    $id = $reference->getId();
                    break;
            }

            $html = $this->convertToStyle($id, $idStyle);
        }

        return $html;
    }

    /**
     *
     */
    protected function renderNoteClass() {
        $result = 'note';

        switch ($this->getStyle('note-font-size')) {
            case 'small':
                $result .= ' small';
                break;
        }

        switch ($this->getStyle('note-text-align')) {
            case 'left':
                $result .= ' left';
                break;

            default:
                $result .= ' justify';
                break;
        }

        return $result;
    }

    /**
     *
     */
    protected function renderNoteIdBase() {
        return $this->renderBase($this->getStyle('note-id-base'));
    }

    /**
     *
     */
    protected function renderNoteIdFont() {
        return $this->renderFont('note-id-font-weight', 'normal', 'note-id-font-style');
    }

    /**
     *
     */
    protected function renderNoteIdFormat() {
        $style = $this->getStyle('note-id-format');
        switch ($style) {
            case '.':
                $result = array('', '.');
                break;

            default:
                $result = $this->renderFormat($style);
                break;
        }

        return $result;
    }

    /**
     *
     */
    protected function renderNoteId($note) {
        $idStyle = $this->getStyle('refnote-id');
        if ($idStyle == 'name') {
            $html = $note->getName();
        }
        else {
            $html = $this->convertToStyle($note->getId(), $idStyle);
        }

        return $html;
    }

    /**
     *
     */
    protected function renderBackRefCaret($singleReference) {
        switch ($this->getStyle('back-ref-caret')) {
            case 'prefix':
                $result = '^ ';
                break;

            case 'merge':
                $result = $singleReference ? '' : '^ ';
                break;

            default:
                $result = '';
                break;
        }

        return $result;
    }

    /**
     *
     */
    protected function renderBackRefBase() {
        return $this->renderBase($this->getStyle('back-ref-base'));
    }

    /**
     *
     */
    protected function renderBackRefFont() {
        return $this->renderFont('back-ref-font-weight', 'bold', 'back-ref-font-style');
    }

    /**
     *
     */
    protected function renderBackRefSeparator() {
        static $html = array('' => ',', 'none' => '');

        $style = $this->getStyle('back-ref-separator');
        if (!array_key_exists($style, $html)) {
            $style = '';
        }

        return $html[$style];
    }

    /**
     *
     */
    protected function renderBackRefId($reference, $index, $singleReference) {
        $style = $this->getStyle('back-ref-format');
        switch ($style) {
            case 'a':
                $result = $this->convertToLatin($index + 1, $style);
                break;

            case '1':
                $result = $index + 1;
                break;

            case 'caret':
                $result = '^';
                break;

            case 'arrow':
                $result = '&uarr;';
                break;

            default:
                $result = $this->renderReferenceId($reference);
                break;
        }

        if ($singleReference && ($this->getStyle('back-ref-caret') == 'merge')) {
            $result = '^';
        }

        return $result;
    }

    /**
     *
     */
    protected function renderBase($style) {
        static $html = array(
            '' => array('<sup>', '</sup>'),
            'text' => array('', '')
        );

        if (!array_key_exists($style, $html)) {
            $style = '';
        }

        return $html[$style];
    }

    /**
     *
     */
    protected function renderFont($weight, $defaultWeight, $style) {
        list($weightOpen, $weightClose) = $this->renderFontWeight($this->getStyle($weight), $defaultWeight);
        list($styleOpen, $styleClose) = $this->renderFontStyle($this->getStyle($style));

        return array($weightOpen . $styleOpen, $styleClose . $weightClose);
    }

    /**
     *
     */
    protected function renderFontWeight($style, $default) {
        static $html = array(
            'normal' => array('', ''),
            'bold' => array('<b>', '</b>')
        );

        if (!array_key_exists($style, $html)) {
            $style = $default;
        }

        return $html[$style];
    }

    /**
     *
     */
    protected function renderFontStyle($style) {
        static $html = array(
            '' => array('', ''),
            'italic' => array('<i>', '</i>')
        );

        if (!array_key_exists($style, $html)) {
            $style = '';
        }

        return $html[$style];
    }

    /**
     *
     */
    protected function renderFormat($style) {
        static $html = array(
            '' => array('', ')'),
            '()' => array('(', ')'),
            ']' => array('', ']'),
            '[]' => array('[', ']'),
            'none' => array('', '')
        );

        if (!array_key_exists($style, $html)) {
            $style = '';
        }

        return $html[$style];
    }

    /**
     *
     */
    protected function convertToStyle($id, $style) {
        switch ($style) {
            case 'a':
            case 'A':
                $result = $this->convertToLatin($id, $style);
                break;

            case 'i':
            case 'I':
                $result = $this->convertToRoman($id, $style);
                break;

            case '*':
                $result = str_repeat('*', $id);
                break;

            default:
                $result = $id;
                break;
        }

        return $result;
    }

    /**
     *
     */
    protected function convertToLatin($number, $case)
    {
        static $alpha = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

        $result = '';
        while ($number > 0) {
            --$number;
            $digit = $number % 26;
            $result = $alpha{$digit} . $result;
            $number = intval($number / 26);
        }

        if ($case == 'a') {
            $result = strtolower($result);
        }

        return $result;
    }

    /**
     *
     */
    protected function convertToRoman($number, $case)
    {
        static $lookup = array(
            'M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400,
            'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40,
            'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1
        );

        $result = '';
        foreach ($lookup as $roman => $value) {
            $matches = intval($number / $value);
            if ($matches > 0) {
                $result .= str_repeat($roman, $matches);
                $number = $number % $value;
            }
        }

        if ($case == 'i') {
            $result = strtolower($result);
        }

        return $result;
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_harvard_renderer extends refnotes_basic_renderer {

    /**
     * Constructor
     */
    public function __construct($namespace) {
        parent::__construct($namespace);
    }

    /**
     *
     */
    public function renderNoteText($data) {
        if (!array_key_exists('title', $data)) {
            return parent::renderNoteText($data);
        }

        // authors, published. //[[url|title.]]// edition. publisher, pages, isbn.
        // authors, published. chapter In //[[url|title.]]// edition. publisher, pages, isbn.
        // authors, published. [[url|title.]] //journal//, volume, publisher, pages, issn.

        $title = $this->renderTitle($data);

        // authors, published. //$title// edition. publisher, pages, isbn.
        // authors, published. chapter In //$title// edition. publisher, pages, isbn.
        // authors, published. $title //journal//, volume, publisher, pages, issn.

        $authors = $this->renderAuthors($data);

        // $authors? //$title// edition. publisher, pages, isbn.
        // $authors? chapter In //$title// edition. publisher, pages, isbn.
        // $authors? $title //journal//, volume, publisher, pages, issn.

        $publication = $this->renderPublication($data, $authors != '');

        if (array_key_exists('journal', $data)) {
            // $authors? $title //journal//, volume, $publication?

            $text = $title . ' ' . $this->renderJournal($data);

            // $authors? $text, $publication?

            $text .= ($publication != '') ? ',' : '.';
        }
        else {
            // $authors? //$title// edition. $publication?
            // $authors? chapter In //$title// edition. $publication?

            $text = $this->renderBook($data, $title);
        }

        // $authors? $text $publication?

        if ($authors != '') {
            $text = $authors . ' ' . $text;
        }

        if ($publication != '') {
            $text .= ' ' . $publication;
        }

        return $text;
    }

    /**
     *
     */
    private function renderTitle($data) {
        $text = $data['title'] . '.';

        if (array_key_exists('url', $data)) {
            $text = '[[' . $data['url'] . '|' . $text . ']]';
        }

        return $text;
    }

    /**
     *
     */
    private function renderAuthors($data) {
        $text = '';

        if (array_key_exists('authors', $data)) {
            $text = $data['authors'];

            if (array_key_exists('published', $data)) {
                $text .= ', ' . $data['published'];
            }

            $text .= '.';
        }

        return $text;
    }

    /**
     *
     */
    private function renderPublication($data, $authors) {
        $part = array();

        if (array_key_exists('publisher', $data)) {
            $part[] = $data['publisher'];
        }

        if (!$authors && array_key_exists('published', $data)) {
            $part[] = $data['published'];
        }

        if (array_key_exists('pages', $data)) {
            $part[] = $data['pages'];
        }

        if (array_key_exists('isbn', $data)) {
            $part[] = 'ISBN ' . $data['isbn'];
        }
        elseif (array_key_exists('issn', $data)) {
            $part[] = 'ISSN ' . $data['issn'];
        }

        $text = implode(', ', $part);

        if ($text != '') {
            $text = rtrim($text, '.') . '.';
        }

        return $text;
    }

    /**
     *
     */
    private function renderJournal($data) {
        $text = '//' . $data['journal'] . '//';

        if (array_key_exists('volume', $data)) {
            $text .= ', ' . $data['volume'];
        }

        return $text;
    }

    /**
     *
     */
    private function renderBook($data, $title) {
        $text = '//' . $title . '//';

        if (array_key_exists('chapter', $data)) {
            $text = $data['chapter'] . '. ' . refnotes_localization::getInstance()->getLang('txt_in_cap') . ' ' . $text;
        }

        if (array_key_exists('edition', $data)) {
            $text .= ' ' . $data['edition'] . '.';
        }

        return $text;
    }
}