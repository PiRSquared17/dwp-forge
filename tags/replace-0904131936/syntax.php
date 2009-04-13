<?php
/**
 * Plugin Replace: Replaces words with DokuWiki snippets
 *
 * Create replace.conf file and populate with words and snippets like in acronyms
 * e.g.
 *
 * HelloWorld **Hello World**
 * This example would replace HelloWorld with bold Hello World
 *
 * HTTPS [[wp>HTTPS]]
 *
 * This example would replace words HTTPS with a Wikipedia link to HTTPS
 *
 * @url        http://www.jdempster.com/
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     James Dempster <letssurf@gmail.com>
 */

if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');

class Syntax_Plugin_Replace extends DokuWiki_Syntax_Plugin {

    function getInfo() {
        return array(
            'author' => 'James Dempster',
            'email'  => 'letssurf@gmail.com',
            'date'   => '2009-04-13',
            'name'   => 'Replacer',
            'desc'   => 'Replaces words thought DokuWiki automaticly from replace.conf with the snippet defined.',
            'url'    => 'http://www.dokuwiki.org/wiki:plugins',
        );
    }

    function getType() { return 'substition'; }

    function getAllowedTypes() {
        return array('container','substition','protected','disabled','formatting','paragraphs');
    }

    function getSort() {
        return 999;
    }

    function Syntax_Plugin_Replace() {
        $this->nesting = false;
        $this->replace = confToHash(DOKU_CONF . 'replace.conf');
    }

    function preConnect() {
        if(!count($this->replace)) return;
        $replacers = array_map('preg_quote', array_keys($this->replace));
        $this->pattern = '\b' . join('|', $replacers) . '\b';
    }

    function connectTo($mode) {
        if(!count($this->replace)) return;
        if(strlen($this->pattern) > 0) {
            $this->Lexer->addSpecialPattern($this->pattern, $mode, 'plugin_replace');
        }
    }

    function handle($match, $state, $pos, &$handler) {
        if ($this->nesting) {
            $handler->_addCall('cdata', array($match), $pos);
        }
        else {
            $this->nesting = true;
            $nestedWriter = & new Doku_Handler_Nest($handler->CallWriter);
            $handler->CallWriter = & $nestedWriter;

            $this->Lexer->parse($this->replace[$match]);

            $nestedWriter->process();
            $handler->CallWriter = & $nestedWriter->CallWriter;
            $handler->calls[count($handler->calls) - 1][2] = $pos;
            $this->nesting = false;
        }
        return false;
    }

    function render($mode, &$renderer, $data) {
        return true;
    }
}
