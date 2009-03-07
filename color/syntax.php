<?php
/**
 * Plugin Color: Sets new colors for text and background.
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <spambox03@mail.ru>
 *             Based on Color plugin by Christopher Smith <chris@jalakai.co.uk>
 *             Based on Hilited plugin by Esther Brunner <esther@kaffeehaus.ch>
 */

/* Must be run within Dokuwiki */
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
require_once(DOKU_PLUGIN . 'syntax.php');

/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_color extends DokuWiki_Syntax_Plugin {

    var $mode;

    /**
     * Constructor
     */
    function syntax_plugin_color() {
        $this->mode = substr(get_class($this), 7);
    }

    /**
     * Return some info
     */
    function getInfo(){
        return array(
            'author' => 'Mykola Ostrovskyy',
            'email'  => 'spambox03@mail.ru',
            'date'   => '2009-03-07',
            'name'   => 'Color Plugin',
            'desc'   => 'Changes text colour and background',
            'url'    => 'http://code.google.com/p/dwp-forge/'
        );
    }

    function getType() {
        return 'formatting';
    }

    function getAllowedTypes() {
        return array('formatting', 'substition', 'disabled');
    }

    function getSort() {
        return 158;
    }

    function connectTo($mode) {
        $this->Lexer->addEntryPattern('<color.*?>(?=.*?</color>)', $mode, $this->mode);
        $this->Lexer->addEntryPattern('<m\d?>(?=.*?</m>)', $mode, $this->mode);
        $this->Lexer->addEntryPattern('!!(?=.*!!)', $mode, $this->mode);
    }

    function postConnect() {
        $this->Lexer->addExitPattern('</color>', $this->mode); 
        $this->Lexer->addExitPattern('</m>', $this->mode);
        $this->Lexer->addExitPattern('!!', $this->mode);
    }

    /**
     * Handle the match
     */
    function handle($match, $state, $pos, &$handler) {
        switch ($state) {
            case DOKU_LEXER_ENTER:
                switch ($match{1}) {
                    case 'c':
                        $data = $this->_handleColorEnter($match);
                        break;

                    case 'm':
                        $data = $this->_handleMarkerEnter($match);
                        break;

                    case '!':
                        $data = $this->_handleMarkerEnter('<m>');
                        break;
                }
                return array($state, $data);

            case DOKU_LEXER_UNMATCHED:
                return array($state, $match);

            case DOKU_LEXER_EXIT:
                return array($state, '');
        }
        return array();
    }

    /**
     * Create output
     */
    function render($mode, &$renderer, $data) {
        if($mode == 'xhtml'){
            list($state, $match) = $data;
            switch ($state) {
                case DOKU_LEXER_ENTER:
                    list($color, $background) = $match;
                    $renderer->doc .= $this->_renderOpenTag($color, $background);
                    break;

                case DOKU_LEXER_UNMATCHED:
                    $renderer->doc .= $renderer->_xmlEntities($match);
                    break;

                case DOKU_LEXER_EXIT :
                    $renderer->doc .= "</span>";
                    break;
            }
            return true;
        }
        return false;
    }

    /**
     *
     */
    function _handleColorEnter($syntax) {
        list($color, $background) = preg_split('/\//', substr($syntax, 6, -1), 2);
        $color = $this->_validateColor($color);
        $background = $this->_validateColor($background);
        return array($color, $background);
    }

    /**
     *
     */
    function _handleMarkerEnter($syntax) {
        preg_match('/<m(\d?)>/', $syntax, $match);
        if ($match[1] != '') {
            $marker = intval($match[1]);
        }
        else {
            $marker = 0;
        }
        //TODO: load marker colors from configuration.
        $color = 'gray';
        $background = 'yellow';
        return array($color, $background);
    }

    /**
     *
     */
    function _renderOpenTag($color, $background) {
        $style = '';
        if ($color != '') {
            $style .= 'color: ' . $color . ';';
        }
        if ($background != '') {
            $style .= 'background-color: ' . $background . ';';
        }
        $html .= '<span';
        if ($style != '') {
            $html .= ' style="' . $style . '"';
        }
        return $html . '>';
    }

    /**
     * Validate color value $c
     * this is cut price validation - only to ensure the basic format is correct and there is nothing harmful
     * three basic formats  "colorname", "#fff[fff]", "rgb(255[%],255[%],255[%])"
     */
    function _validateColor($c) {
        $c = trim($c);

        $pattern = "/^\s*(
            ([a-zA-z]+)|                                #colorname - not verified
            (\#([0-9a-fA-F]{3}|[0-9a-fA-F]{6}))|        #colorvalue
            (rgb\(([0-9]{1,3}%?,){2}[0-9]{1,3}%?\))     #rgb triplet
            )\s*$/x";

        if (preg_match($pattern, $c)) {
            return trim($c);
        }

        return '';
    }
}
