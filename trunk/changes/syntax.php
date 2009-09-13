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
        );

        $match = explode('&',$match);
        foreach($match as $m){
            if(is_numeric($m)){
                $data['count'] = (int) $m;
            }else{
                if (preg_match('/(\w+)\s*=(.+)/', $m, $temp) == 1){
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
        switch($name){
            case 'count': $data[$name] = intval($value); break;
            case 'ns': $data[$name] = cleanID($value); break;
            case 'type' :
                if(array_key_exists($value, $types)){
                    $data[$name] = $types[$value];
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

}

//Setup VIM: ex: et ts=4 enc=utf-8 :
