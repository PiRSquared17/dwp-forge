<?php

/**
 * Spacer Plugin
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <spambox03@mail.ru>
 */

/* Must be run within Dokuwiki */
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
require_once(DOKU_PLUGIN . 'syntax.php');

class syntax_plugin_spacer extends DokuWiki_Syntax_Plugin {

    var $mode;

    function syntax_plugin_spacer() {
        $this->mode = substr(get_class($this), 7);
    }

    function getInfo() {
        return array(
            'author' => 'Mykola Ostrovskyy',
            'email'  => 'spambox03@mail.ru',
            'date'   => '2009-02-14',
            'name'   => 'Spacer Plugin',
            'desc'   => 'Allows to insert horizontal spacers.',
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
        $this->Lexer->addSpecialPattern('<spacer\s+.+?>', $mode, $this->mode);
    }

    function handle($match, $state, $pos, &$handler) {
        if ($state == DOKU_LEXER_SPECIAL) {
            if (preg_match('/<spacer\s+(.+?)>/', $match, $match) != 1) {
                return false;
            }
            return array(trim($match[1]));
        }
        return false;
    }

    function render($mode, &$renderer, $data) {
        if ($mode == 'xhtml') {
            $renderer->doc .= '<span style="padding-left:' . $data[0] . ';"></span>';
            return true;
        }
        return false;
    }
}
