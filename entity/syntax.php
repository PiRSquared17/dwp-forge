<?php
/**
 * Plugin Entity: Allows to insert HTML entities.
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
class syntax_plugin_entity extends DokuWiki_Syntax_Plugin {

    function getInfo() {
        return array(
            'author' => 'Mykola Ostrovskyy',
            'email'  => 'spambox03@mail.ru',
            'date'   => '2007-10-26',
            'name'   => 'Entity Plugin',
            'desc'   => 'Allows to insert HTML entities',
            'url'    => 'http://code.google.com/p/dwp-forge/'
        );
    }

    function getType() {
        return 'formatting';
    }

    function getSort() {
        return 5;
    }

    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('&&(?:#\d+|\w+);;',$mode,'plugin_entity');
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
