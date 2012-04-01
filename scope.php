<?php

/**
 * Plugin RefNotes: Scope
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <spambox03@mail.ru>
 */

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_scope_limits {
    public $start;
    public $end;

    /**
     * Constructor
     */
    public function __construct($start, $end = -1000) {
        $this->start = $start;
        $this->end = $end;
    }

    /**
     *
     */
    public function isOpen() {
        return $this->end == -1000;
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_scope {

    private $namespace;
    private $id;
    private $limits;
    private $note;
    private $notes;
    private $references;

    /**
     * Constructor
     */
    public function __construct($namespace, $id, $start = -1, $end = -1000) {
        $this->namespace = $namespace;
        $this->id = $id;
        $this->limits = new refnotes_scope_limits($start, $end);
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
    public function getNamespaceName() {
        return $this->namespace->getName();
    }

    /**
     *
     */
    public function getLimits() {
        return $this->limits;
    }

    /**
     *
     */
    public function isOpen() {
        return $this->limits->end == -1000;
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
            if (is_int($name)) {
                $getter = 'getId';
            }
            else {
                $getter = 'getName';
            }

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
