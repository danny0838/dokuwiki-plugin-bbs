<?php
/**
 * <text> tag that embeds plain text with linebreaks
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_bbs extends DokuWiki_Syntax_Plugin {

    function getType() { return 'protected';}
    function getPType() { return 'normal';}
    function getSort() { return 20; }

    /**
     * Connect pattern to lexer
     */
    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('<bbs>.*?</bbs>', $mode, 'plugin_bbs');
    }

    /**
     * Handle the match
     */
    function handle($match, $state, $pos, &$handler){
        return substr($match,5,-6);
    }

    /**
     * Create output
     */
    function render($format, &$renderer, $data) {
        if($format == 'xhtml'){
            $data = trim( $renderer->_xmlEntities($data), "\n");
            $data = $this->_parse_bbs($data);
            $renderer->doc .= "<pre class=\"bbs\">".DOKU_LF;
            $renderer->doc .= $data.DOKU_LF;
            $renderer->doc .= "</pre>".DOKU_LF;
            return true;
        }else if($format == 'metadata'){
            $data = $this->_parse_bbs_metadata($data);
            $renderer->doc .= $data.DOKU_LF;
            return true;
        }
        return false;
    }

    function _parse_bbs($s) {
        $this->tags = array();
        $s = preg_replace( "/\x1B/", '&#27;', $s);
        // UAO to unicode
        $s = preg_replace_callback( "/[\x{E024}-\x{F848}]/u", array($this,"_bbs_uao_to_unicode"), $s);
        // Special formats: : text, > text, ※text
        $s = preg_replace( "/^((?::|&gt;)(?: |&nbsp;).*)$/m", '&#27;[0;36m$1&#27;[m', $s );
        $s = preg_replace( "/^(※.*)$/m", '&#27;[0;32m$1&#27;[m', $s );
        // Control codes
        $s = preg_replace_callback( "/&#27;\[([0-9;]*)(.)/", array($this,"_bbs_parse_tag"), $s );
        // Links
        $s = preg_replace_callback( "/(https?|ftp|telnet):\/\/(?:<.*?>|.)*?(?= |\n|$)/", array($this,"_bbs_parse_link"), $s );
        return $s;
    }
    
    function _parse_bbs_metadata($s) {
        $s = preg_replace( "/\x1B/", '&#27;', $s);
        // UAO to unicode
        $s = preg_replace_callback( "/[\x{E024}-\x{F848}]/u", array($this,"_bbs_uao_to_unicode"), $s);
        // Control codes
        $s = preg_replace( "/&#27;\[([0-9;]*)(.)/", "", $s );
        return $s;
    }

    function _bbs_parse_tag($match) {
        if ($match[2]!='m') return '';
        $this->ret = '';
        $args = explode(';',$match[1]);
        if (empty($args)) {
            $this->_bbs_close_tag();
            return $this->ret;
        }
        for ($i=0,$I=count($args);$i<$I;++$i) {
            $s = (int)$args[$i];
            if ($s==0) {
                $this->_bbs_add_tag();
                $this->_bbs_close_tag();
            }
            else if ($s==1) {$this->tags[bright] = true;}
            else if ($s==3) {$this->tags[italic] = true;}
            else if ($s==5) {$this->tags[blink] = true;}
            else if ($s==8) {$this->tags[hidden] = true;}
            else if ($s>=30 && $s<=37) {$this->tags[fg] = $s;}
            else if ($s>=40 && $s<=47) {$this->tags[bg] = $s;}
        }
        $this->_bbs_add_tag();
        return $this->ret;
    }

    function _bbs_add_tag() {
        $c = array();
        if ($this->tags[fg]) $c[] = ($this->tags[bright]?'fgb':'fgd').$this->tags[fg];
        if ($this->tags[bg]) $c[] = 'bg'.$this->tags[bg];
        if ($this->tags[italic]) $c[] = 'italic';
        if ($this->tags[blink]) $c[] = 'blink';
        if ($this->tags[hidden]) $c[] = 'hidden';
        if (!empty($c)) {
            $this->ret .= '<span class="' . implode(' ', $c) . '">';
            $this->tags[count]++;
        }
    }

    function _bbs_close_tag() {
        $count = $this->tags[count];
        if ($count) $this->ret .= str_repeat( '</span>', $count+1 );
        $this->tags = array();
    }

    function _bbs_parse_link($match) {
        $url = preg_replace( "/<.*?>/", '', $match[0]);
        return '<a class="' . $match[1] . '" href="' . $url . '">' . $match[0] . '</a>';
    }

    function _bbs_uao_to_unicode($match) {
        require_once( dirname(__FILE__).'/'.'uao.php' );
        global $uao_list;
        $replaced = $uao_list[$match[0]];
        if ($replaced) return $replaced;
        return $match[0];
    }
}
