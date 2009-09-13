<?php

if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');

/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_changes extends DokuWiki_Syntax_Plugin {

    /**
     * return some info
     */
    function getInfo(){
        return array(
            'author' => 'Andreas Gohr',
            'email'  => 'gohr@cosmocode.de',
            'date'   => '2009-03-12',
            'name'   => 'Changes Plugin',
            'desc'   => 'List the most recent changes of the wiki',
            'url'    => 'http://www.dokuwiki.org/plugin:changes',
        );
    }
    /**
     * What kind of syntax are we?
     */
    function getType(){
        return 'substition';
    }
    /**
     * Where to sort in?
     */
    function getSort(){
        return 105;
    }
    /**
     * Connect pattern to lexer
     */
    function connectTo($mode) {
      $this->Lexer->addSpecialPattern('\{\{changes>[^}]*\}\}',$mode,'plugin_changes');
    }
    /**
     * Handle the match
     */
    function handle($match, $state, $pos, &$handler){
        $match = substr($match,10,-2);

        $data = array(
            'ns' => '',
            'count' => 10,
            'type' => '',
            'render' => 'list',
            'render-flags' => array(),
        );

        $match = explode('&',$match);
        foreach($match as $m){
            if(is_numeric($m)){
                $data['count'] = (int) $m;
            }else{
                if(preg_match('/(\w+)\s*=(.+)/', $m, $temp) == 1){
                    $this->handleNamedParameter($temp[1], trim($temp[2]), $data);
                }else{
                    $data['ns'] = cleanID($m);
                }
            }
        }

        return $data;
    }

    /**
     * Handle parameters that are specified uing <name>=<value> syntax
     */
    function handleNamedParameter($name, $value, &$data) {
        static $types = array('edit' => 'E', 'create' => 'C');
        static $renderers = array('list', 'pagelist');
        switch($name){
            case 'count': $data[$name] = intval($value); break;
            case 'ns': $data[$name] = cleanID($value); break;
            case 'type':
                if(array_key_exists($value, $types)){
                    $data[$name] = $types[$value];
                }
                break;
            case 'render':
                if(preg_match('/(\w+)(?:\((.*)\))?/', $value, $match) == 1){
                    if(in_array($match[1], $renderers)){
                        $data[$name] = $match[1];
                        $flags = trim($match[2]);
                        if($flags != ''){
                            $data['render-flags'] = preg_split('/\s*,\s*/', $flags);
                        }
                    }
                }
                break;
        }
    }

    /**
     * Create output
     */
    function render($mode, &$R, $data) {
        $R->info['cache'] = false;
        if($mode != 'xhtml') return false;

        $recents = getRecents(0,$data['count'],$data['ns']);
        if(!count($recents)) return true;

        if($data['type'] != ''){
            $recents = $this->filterOnChangeType($recents, $data['type']);
            if(!count($recents)) return true;
        }

        switch($data['render']){
            case 'list': $this->renderSimpleList($recents, $R); break;
            case 'pagelist': $this->renderPageList($recents, $R, $data['render-flags']); break;
        }
        return true;
    }

    /**
     *
     */
    function filterOnChangeType($recents, $type) {
        $result = array();
        foreach($recents as $rec){
            if($rec['type'] == $type){
                $result[] = $rec;
            }
        }
        return $result;
    }

    /**
     *
     */
    function renderPageList($recents, &$R, $flags) {
        $pagelist =& plugin_load('helper', 'pagelist');
        if($pagelist){
            $pagelist->setFlags($flags);
            $pagelist->startList();
            foreach($recents as $rec){
                $pagelist->addPage(array('id' => $rec['id']));
            }
            $R->doc .= $pagelist->finishList();
        }else{
            // Fallback to the simple list renderer
            $this->renderSimpleList($recents, $R);
        }
    }

    /**
     *
     */
    function renderSimpleList($recents, &$R) {
        $R->listu_open();
        foreach($recents as $rec){
            $R->listitem_open(1);
            $R->listcontent_open();
            $R->internallink($rec['id']);
            $R->cdata(' '.$rec['sum']);
            $R->listcontent_close();
            $R->listitem_close();
        }
        $R->listu_close();
    }
}

//Setup VIM: ex: et ts=4 enc=utf-8 :
