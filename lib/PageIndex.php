<?php
/**
 * A Simple text based TitleIndexer class
 *
 * @since 2010/08/16
 * @author Won-Kyu Park <wkpark@kldp.org>
 * @license GPLv2
 */

class PageIndex {
    var $text_dir = '';

    function PageIndex($name = 'pageindex')
    {
        global $Config;

        $this->text_dir = $Config['text_dir'];
        $this->cache_dir = $Config['cache_dir'] . '/'.$name;
        if (!is_dir($this->cache_dir)) {
            $om = umask(000);
            _mkdir_p($this->cache_dir, 0777);
            umask($om);
        }
        $this->pagelst = $this->cache_dir . '/'.$name.'.lst';
        $this->pageidx = $this->cache_dir . '/'.$name.'.idx';
        $this->pagecnt = $this->cache_dir . '/'.$name.'.cnt';
        $this->pagelck = $this->cache_dir . '/'.$name.'.lock';
    }

    function mtime()
    {
        return @filemtime($this->pageidx);
    }

    /**
     * update the lifetime of the index
     *
     * @access public
     */
    function update()
    {
        return touch($this->pageidx);
    }

    function init()
    {
        global $DBInfo;

        // check if a tmp file already exists and outdated or not.
        if (file_exists($this->pageidx.'.tmp')) {
            if (time() - filemtime($this->pageidx.'.tmp') < 60*30)
                return false;
        }

        $dh = opendir($this->text_dir);
        if (!is_resource($dh)) return false;

        $fidx = fopen($this->pageidx.'.tmp', 'a+b');
        if (!is_resource($fidx)) {
            closedir($dh);
            return false;
        }
        $flst = fopen($this->pagelst.'.tmp', 'a+b');
        if (!is_resource($flst)) {
            closedir($dh);
            fclose($fidx);
            return false;
        }

        $fcnt = fopen($this->pagecnt.'.tmp', 'a+b');
        if (!is_resource($flst)) {
            closedir($dh);
            fclose($flst);
            fclose($fidx);
            return false;
        }

        ftruncate($flst, 0);
        ftruncate($fidx, 0);
        ftruncate($fcnt, 0);

        $idx_data = '';
        $lst_data = '';
        $total = 0;
        $counter = 0;
        $fseek = 0;
        $pages = array();
        while(($f = readdir($dh)) !== false) {
            if ((($p = strpos($f, '.')) !== false or $f == 'RCS' or $f == 'CVS') and is_dir($this->text_dir .'/'. $f)) continue;
            $counter++;
            $total++;

            $idx_data.= pack('N', $fseek);
            $pagename = $DBInfo->keyToPagename($f)."\n";
            $fseek += strlen($pagename);
            $lst_data.= $pagename;

            if ($counter > 1000) {
                fwrite($fidx, $idx_data);
                fwrite($flst, $lst_data);
                $idx_data = '';
                $lst_data = '';
                $counter = 0;
            }
        }
        if (!empty($lst_data)) {
            fwrite($fidx, $idx_data);
            fwrite($flst, $lst_data);
        }
        fclose($fidx);
        fclose($flst);
        closedir($dh);

        fwrite($fcnt, $total); // total page counter
        fclose($fcnt);

        if (getenv('OS') == 'Windows_NT') {
            @unlink($this->pagelst);
            @unlink($this->pageidx);
            @unlink($this->pagecnt);
        }
        rename($this->pagelst.'.tmp', $this->pagelst);
        rename($this->pageidx.'.tmp', $this->pageidx);
        rename($this->pagecnt.'.tmp', $this->pagecnt);
    }

    function getPagesByIds($ids)
    {
        $fidx = fopen($this->pageidx, 'r');
        if (!is_resource($fidx)) return false;
        $flst = fopen($this->pagelst, 'r');
        if (!is_resource($flst)) {
            fclose($fidx);
            return false;
        }
        $count = @file_get_contents($this->pagecnt);
        if ($count === false) {
            fclose($fidx);
            fclose($flst);
            return false;
        }

        $pages = array();
        foreach((array)$ids as $id) {
            if ($id >= $count) continue;
            fseek($fidx, $id * 4);
            $seek = unpack('N', fread($fidx, 4));
            fseek($flst, $seek[1]);
            $pg = fgets($flst, 2048);
            $pages[] = substr($pg, 0, -1);
        }
        fclose($fidx);
        fclose($flst);

        return $pages;
    }

    function pageCount()
    {
        $count = @file_get_contents($this->pagecnt);
        return $count;
    }

    function sort()
    {
        // check if a tmp file already exists and outdated or not.
        if (file_exists($this->pageidx.'.tmp')) {
            if (time() - filemtime($this->pageidx.'.tmp') < 60*30)
                return false;
        }

        $lock = fopen($this->pagelck, 'w');
        if (!is_resource($lock)) return false;
        flock($lock, LOCK_EX);

        $fidx = fopen($this->pageidx.'.tmp', 'a+b');
        if (!is_resource($fidx)) {
            flock($lock, LOCK_UN);
            fclose($lock);
            return false;
        }
        $flst = fopen($this->pagelst.'.tmp', 'a+b');
        if (!is_resource($flst)) {
            fclose($fidx);
            flock($lock, LOCK_UN);
            fclose($lock);
            return false;
        }

        $tmp = file_get_contents($this->pagelst);
        if ($tmp === false) {
            fclose($flst);
            fclose($fidx);
            flock($lock, LOCK_UN);
            fclose($lock);
            return false;
        }
        ftruncate($flst, 0);
        ftruncate($fidx, 0);

        $lst = explode("\n", $tmp);
        $tmp = '';
        array_pop($lst); // trash last empty line
        sort($lst); // sort list

        $idx_data = '';
        $counter = 0;
        $fseek = 0;
        foreach ($lst as $l) {
            $counter++;
            $idx_data.= pack('N', $fseek);
            $len = strlen($l) + 1; // pagename + "\n"
            $fseek += $len;

            if ($counter > 1000) {
                fwrite($fidx, $idx_data);
                $idx_data = '';
                $counter = 0;
            }
        }

        if (!empty($idx_data))
            fwrite($fidx, $idx_data);

        fwrite($flst, implode("\n", $lst)."\n");
        fclose($fidx);
        fclose($flst);

        if (getenv('OS') == 'Windows_NT') {
            @unlink($this->pagelst);
            @unlink($this->pageidx);
        }
        rename($this->pagelst.'.tmp', $this->pagelst);
        rename($this->pageidx.'.tmp', $this->pageidx);

        flock($lock, LOCK_UN);
        fclose($lock);
    }

    function addPage($pagename)
    {
        $lock = fopen($this->pagelck, 'w');
        if (!is_resource($lock)) return false;
        flock($lock, LOCK_EX);

        $fidx = fopen($this->pageidx, 'a+b');
        if (!is_resource($fidx)) {
            flock($lock, LOCK_UN);
            fclose($lock);
            return false;
        }
        $flst = fopen($this->pagelst, 'a+b');
        if (!is_resource($flst)) {
            fclose($fidx);
            flock($lock, LOCK_UN);
            fclose($lock);
            return false;
        }

        $fcnt = fopen($this->pagecnt, 'a+b');
        if (!is_resource($fcnt)) {
            fclose($flst);
            fclose($fidx);
            flock($lock, LOCK_UN);
            fclose($lock);
            return false;
        }
        $total = fgets($fcnt, 1024);

        fseek($flst, 0, SEEK_END);
        $seek = ftell($flst); // get last position of the page list

        fwrite($fidx, pack('N', $seek)); // add a new page idx.
        fwrite($flst, $pagename."\n"); // add a new page name.
        fclose($fidx);
        fclose($flst);

        ftruncate($fcnt, 0);
        fwrite($fcnt, ++$total); // increase the total page counter
        fflush($fcnt);
        fclose($fcnt);

        flock($lock, LOCK_UN);
        fclose($lock);
        return $total;
    }

    function deletePage($pagename)
    {
        $lock = fopen($this->pagelck, 'w');
        if (!is_resource($lock)) return false;
        flock($lock, LOCK_EX);

        $fidx = fopen($this->pageidx, 'r+');
        if (!is_resource($fidx)) {
            flock($lock, LOCK_UN);
            fclose($lock);
            return false;
        }

        $flst = fopen($this->pagelst, 'r+');
        if (!is_resource($flst)) {
            fclose($fidx);
            flock($lock, LOCK_UN);
            fclose($lock);
            return false;
        }

        // get the page counter
        $fcnt = fopen($this->pagecnt, 'a+b');
        if (!is_resource($fcnt)) {
            fclose($flst);
            fclose($fidx);
            flock($lock, LOCK_UN);
            fclose($lock);
            return false;
        }
        $total = fgets($fcnt, 1024);

        fseek($flst, 0, SEEK_END);
        $endlst = ftell($flst);

        $seek = 0;
        $nseek = -1;
        fseek($flst, 0, SEEK_SET); // rewind

        /* slow method *
        for ($i = 0; $i < $total; $i++) {
            $page = substr(fgets($flst, 2048), 0, -1);
            if ($page[0] == $pagename[0] and $page == $pagename) {
                $len = strlen($page) + 1;
                $nseek = ftell($flst);
                $seek = $nseek - $len;
                break;
            }
        }
        /* */

        /* fast method */
        $chunk = 10000 - 1; // chunk size
        $is = $ie = 0; // index start/end
        $ss = $se = 0; // seek start/end
        fseek($flst, 0, SEEK_SET);
        while ($ie < $total - 1) {
            $ie = $is + $chunk;
            if ($ie >= $total) $ie = $total - 1;
            fseek($fidx, $ie * 4, SEEK_SET);
            $dum = unpack('N', fread($fidx, 4));
            $se = $dum[1];

            $tmp = "\n". fread($flst, $se - $ss);
            $addtmp = fgets($flst, 1024); // include last chunk
            $tmp.= $addtmp;
            $se+= strlen($addtmp);
            if (($p = strpos($tmp, "\n".$pagename."\n")) !== false) {
                $seek = $ss + $p;
                $nseek = $seek + strlen($pagename) + 1;
                fseek($flst, $nseek, SEEK_SET);

                $i = $is;
                if ($p > 1) $i+= count(explode("\n", substr($tmp, 1, $p - 1)));
                break;
            }
            $ss = $se;
            $is = $ie + 1;
        }
        /* */

        if ($nseek == -1) {
            fclose($fcnt);
            fclose($flst);
            fclose($fidx);
            flock($lock, LOCK_UN);
            fclose($lock);
            return false;
        }

        // fix list
        $remain = fread($flst, $endlst - $nseek + 1);
        fseek($flst, $seek, SEEK_SET);
        fwrite($flst, $remain);
        $size = ftell($flst);
        ftruncate($flst, $size);
        fclose($flst);

        // fix idx
        fseek($fidx, $i * 4, SEEK_SET);

        fread($fidx, 4); // trash deleted page idx
        $idx_data = '';
        $j = $i;
        while (++$j < $total and $idata = fread($fidx, 4)) {
            $idx = unpack('N', $idata);
            $id = $idx[1] - $len;
            $idx_data.= pack('N', $id);
        }

        fseek($fidx, $i * 4, SEEK_SET);
        fwrite($fidx, $idx_data);
        $size = ftell($fidx);
        ftruncate($fidx, $size);
        fclose($fidx);

        ftruncate($fcnt, 0);
        fwrite($fcnt, --$total); // total page counter
        fflush($fcnt);
        fclose($fcnt);

        flock($lock, LOCK_UN);
        fclose($lock);
        return $total;
    }

    function renamePage($oldname, $newname)
    {
        $this->deletePage($oldname);
        $this->addPage($newname);
    }

    function getLikePages($needle, $limit = 100)
    {
        if (!isset($needle[0])) return false; // null needle

        $total = file_get_contents($this->pagecnt);
        if ($total === false) return false;

        $flst = fopen($this->pagelst, 'r');
        if (!is_resource($flst)) {
            return false;
        }

        $fidx = fopen($this->pageidx, 'r');
        if (!is_resource($fidx)) {
            fclose($flst);
            return false;
        }

        $pages = array();

        $pre = '.*';
        $suf = '.*';
        if ($needle[0] == '^') {
            $pre = '';
            $needle = substr($needle, 1);
        }
        if (substr($needle, -1) == '$') {
            $suf = '';
            $needle = substr($needle, 0, -1);
        }

        $chunk = 10000 - 1; // chunk size
        $is = $ie = 0; // index start/end
        $ss = $se = 0; // seek start/end
        fseek($flst, 0, SEEK_SET);
        while ($ie < $total - 1) {
            $ie = $is + $chunk;
            if ($ie >= $total) $ie = $total - 1;
            fseek($fidx, $ie * 4, SEEK_SET);
            $dum = unpack('N', fread($fidx, 4));
            $se = $dum[1];

            $tmp = fread($flst, $se - $ss);
            $addtmp = fgets($flst, 1024); // include last chunk
            $tmp.= $addtmp;
            $se+= strlen($addtmp);
            if (preg_match_all('/^(?:'.$pre.$needle.$suf.')$/uim', $tmp, $match)) {
                $pages = array_merge($pages, $match[0]);
                if (!empty($limit) and count($pages) > $limit) break;
            }
            $ss = $se;
            $is = $ie + 1;
        }

        fclose($flst);
        fclose($fidx);

        return $pages;
    }
}


// vim:et:sts=4:sw=4:
