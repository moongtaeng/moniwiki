<?php
// Copyright 2004 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a RcsLite versioning plugin for the MoniWiki
//
// $Id$

class Version_RcsLite extends Version_RCS {
  var $DB;

  function Version_RcsLite($DB) {
    include_once('rcslite.php');

    $this->rcs=new RcsLite($DB->rcs_dir,$DB->rcs_user);
    $this->DB=$DB;
  }

  function co($pagename,$rev,$opt='') {
    $filename= $this->_filename($pagename);

    $this->rcs->_process($filename);

    if ($this->rcs->_author[$rev]) 
      $out=$this->rcs->getRevision($rev);

    return $out;
  }

  function ci($pagename,$log) {
    $filename=$this->_filename($pagename);
    $this->rcs->_process($filename);
    $this->rcs->addRevisionPage($log);
  }

  function rlog($pagename,$rev='',$opt='',$oldopt='') {
    $filename=$this->_filename($pagename);

    $this->rcs->_process($filename);

    return $this->rcs->rlog($rev,$opt,$oldopt);
  }

  function diff($pagename,$rev,$rev2) {
    $filename=$this->_filename($pagename);
    $this->rcs->_process($filename);

    #print $rev.":".$rev2;
    $out=$this->rcs->revisionDiff($rev,$rev2,'udiff'); // XXX

    return $out;
  }

  function purge($pagename,$rev) {
  }

  function delete($pagename) {
    $keyname=$DB->_getPageKey($pagename);
    @unlink($DB->text_dir."/RCS/$keyname,v");
  }

  function rename($pagename,$new) {
    $keyname=$DB->_getPageKey($new);
    $oname=$DB->_getPageKey($pagename);
    rename($DB->text_dir."/RCS/$oname,v",$DB->text_dir."/RCS/$keyname,v");
  }

  function get_rev($pagename,$mtime='',$last=0) {
    $filename=$this->_filename($pagename);
    $this->rcs->_process($filename);

    if ($last==1)
      return $this->rcs->_head;
    if ($mtime) {
      #print gmdate('Y/m/d H:i:s',$mtime);
      if ($mtime > $this->rcs->_date[$this->rcs->_head])
         return $this->_head;
      foreach ($this->rcs->_date as $rev=>$date) {
         if ($mtime > $date) {
            return $rev;
         }
      }
      return $this->_head;
    } else {
      return $this->rcs->_next[$this->rcs->_head];
    }

    $out = $this->rlog($pagename,'',$opt);
    if ($out) {
      $lines=explode("\n",$out);
      foreach ($lines as $line) {
        preg_match("/^revision\s+([\d\.]+)/",$line,$match);
        if ($rev == $match[1])
            continue;
        else {
          $rev=$match[1];
          break;
	}
      }
    }
    if ($rev) return $rev;
    return $this->rcs->_head;
  }
}

?>