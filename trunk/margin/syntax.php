<?php
/**
 * Plugin Margin: Allows to add some margin on left side of a text block.
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <spambox03@mail.ru>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');

/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_margin extends DokuWiki_Syntax_Plugin {

    var $mode;

    /**
     * Constructor
     */
    function syntax_plugin_margin(){
        $this->mode = substr(get_class($this), 7);
    }

    /**
     * Return some info
     */
    function getInfo() {
        return array(
            'author' => 'Mykola Ostrovskyy',
            'email'  => 'spambox03@mail.ru',
            'date'   => '2008-11-17',
            'name'   => 'Margin Plugin',
            'desc'   => 'Allows to add some margin on left side of a text block.',
            'url'    => 'http://code.google.com/p/dwp-forge/'
        );
    }

    /**
     * What kind of syntax are we?
     */
    function getType() {
        return 'container';
    }

    function getPType() {
        return 'block';
    }

    /**
     * What modes are allowed within our mode?
     */
    function accepts($mode) {
        /* Ensure that our own mode can be nested */
        if ($mode == $this->mode) {
            return true;
        }
        return parent::accepts($mode);
    }

    /**
     * What modes are allowed within our mode?
     */
    function getAllowedTypes() {
        return array (
            'container',
            'substition',
            'protected',
            'disabled',
            'formatting',
            'paragraphs'
        );
    }

    /**
     * Where to sort in?
     */
    function getSort() {
        return 5;
    }

    function connectTo($mode) {
        $this->Lexer->addEntryPattern('<margin\s+.+?>(?=.*?</margin>)', $mode, $this->mode);
    }

    function postConnect() {
        $this->Lexer->addExitPattern('</margin>', $this->mode);
    }

    /**
     * Handle the match
     */
    function handle($match, $state, $pos, &$handler) {
        switch ($state) {
            case DOKU_LEXER_ENTER:
                if (preg_match('/<margin\s+(.+?)>/', $match, $match) != 1) {
                    return false;
                }
                return array($state, trim($match[1]));

            case DOKU_LEXER_UNMATCHED:
                return array($state, $match);

            case DOKU_LEXER_EXIT:
                return array($state, '');
        }
        return false;
    }

    /**
     * Create output
     */
    function render($mode, &$renderer, $data) {
        if ($mode == 'xhtml') {
            switch ($data[0]) {
                case DOKU_LEXER_ENTER:
                    $renderer->doc .= '<div style="margin-left: ' . $data[1] . ';">';
                    break;

                case DOKU_LEXER_UNMATCHED:
                    $renderer->doc .= $renderer->_xmlEntities($data[1]);
                    break;

                case DOKU_LEXER_EXIT:
                    $renderer->doc .= '</div>';
                    break;
            }
            return true;
        }
        return false;
    }
}
