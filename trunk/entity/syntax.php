<?php

/**
 * Entity Plugin
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <spambox03@mail.ru>
 */

/* Must be run within Dokuwiki */
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
require_once(DOKU_PLUGIN . 'syntax.php');

class syntax_plugin_entity extends DokuWiki_Syntax_Plugin {

    var $mode;

    function syntax_plugin_entity() {
        $this->mode = substr(get_class($this), 7);
    }

    function getInfo() {
        return array(
            'author' => 'Mykola Ostrovskyy',
            'email'  => 'spambox03@mail.ru',
            'date'   => '2009-02-14',
            'name'   => 'Entity Plugin',
            'desc'   => 'Allows to insert HTML entities.',
            'url'    => 'http://code.google.com/p/dwp-forge/'
        );
    }

    function getType() {
        return 'substition';
    }

    function getSort() {
        return 5;
    }

    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('&&(?:#\d+|\w+);;', $mode, $this->mode);
    }

    function handle($match, $state, $pos, &$handler) {
        if ($state == DOKU_LEXER_SPECIAL) {
            return array($state, substr($match, 1, -1));
        }
        return false;
    }

    function render($mode, &$renderer, $data) {
        if ($mode == 'xhtml') {
            list($state, $match) = $data;
            if ($state == DOKU_LEXER_SPECIAL) {
                $renderer->doc .= $match;
            }
            return true;
        }
        return false;
    }
}
