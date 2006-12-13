<?php
// Copyright 2006 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a Rating plugin for the MoniWiki
//
// Date: 2006-08-16
// Name: Rating
// Description: Rating Plugin
// URL: MoniWiki:RatingPlugin
// Version: $Revision$
// License: GPL
//
// Usage: [[Rating(totalscore,count)]] or [[Rating(initial score)]]
//
// $Id$

function macro_Rating($formatter,$value='',$options=array()) {
    global $Config;
    $rating_script=&$GLOBALS['rating_script'];

    $mid='&amp;mid='.base64_encode($formatter->mid.',Rating,'.$value);

    $val=explode(',',$value);
    if (sizeof($val)>=2) {
        $total=$val[0];
        $count=$val[1];
    } else
        $total=$val[0];
    $count=max(1,$count);
    $value=$total/$count; // averaged value
    $value=(!empty($value) and 0 < $value and 6 > $value) ? $value:0;

    $iconset='star';
    $imgs_dir=$Config['imgs_dir'].'/plugin/Rating/'.$iconset;
    $script=<<<EOF
<script type="text/javascript">
/*<![CDATA[*/
function showstars(obj, n, desc) {
    var my = obj.parentNode.parentNode;
    var c = my.getElementsByTagName('img');
    for( i=0; i < c.length; i++ ) {
        if (i < n)
            c[i].src = "$imgs_dir/star1.png"
        else
            c[i].src = "$imgs_dir/star0.png"
    }
    my.getElementsByTagName('span')[0].innerHTML = desc ? ' ' + desc:'';
}

/*]]>*/
</script>
EOF;

    $star='<span class="rating">';
    $msg=array(
        1=>_("Awful!"),
        2=>_("Not the worst ever."),
        3=>_("Not bad!"),
        4=>_("Useful!"),
        5=>_("Very Gooood!"));

    for ($i=1;$i<=5;++$i) {
        $t=($i <= $value) ? '1':'0';
        $alt=$t ? '{*}':'{o}';
        $star.='<a href="?action=rating'.$mid.'&amp;rating='.$i.'">'.
            '<img alt="'.$alt.'" src="'.$imgs_dir.'/star'.$t.'.png" '.
            'onmouseover="showstars(this,'.$i.
            ',\''.$msg[$i].'\')" onmouseout="showstars(this,'.intval($value).',\'\')" '.
            'border="0" class="star" /></a>';
    }
    $star.=<<<EOF
<span class="rating-desc" style="font-size: 16px;"></span>
</span>
EOF;
    if ($rating_script) return $star;
    $rating_script=1;
    return $script.$star;
}

function do_rating($formatter,$options) {
    global $DBInfo;
    if ($options['id'] == 'Anonymous') {
        $options['msg'].="\n"._("Please Login or make your ID on this Wiki ;)");
        do_invalid($formatter,$options);
        return;
    }
    $formatter->send_header('',$options);

    $raw=$formatter->page->get_raw_body();

    list($nth,$dum,$v)=explode(',', base64_decode($options['mid']),3);

    $val=explode(',',$v);
    if (sizeof($val)>=2) {
        $total=$val[0];
        $count=$val[1];
    } else
        $total=$val[0];
    $count=max(1,$count);
    $value=$total/$count; // averaged value
    $value=(!empty($value) and 0 < $value and 6 > $value) ? $value:0;
    ++$count;

    $check='[['.$dum.'('.$v.')]]';
    $rating=$options['rating'] ? (int)$options['rating']:1;
    $rating=min(5,max(0,$rating));

    $total+=$rating; // increase total rating

    $raw=str_replace("\n","\1",$raw);
    $chunk=preg_split("/({{{.+}}})/U",$raw,-1,PREG_SPLIT_DELIM_CAPTURE);
    #print '<pre>';
    #print_r($chunk);
    #print '</pre>';
    $nc='';
    $k=1;
    $i=1;
    foreach ($chunk as $c) {
        if ($k%2) {
            $nc.=$c;
        } else {
            $nc.="\7".$i."\7";
            $blocks[$i]=str_replace("\1","\n",$c);
            ++$i;
        }
        $k++;
    }
    $nc=str_replace("\1","\n",$nc);
    $chunk=preg_split('/((?!\!)\[\[.+\]\])/U',$nc,-1,PREG_SPLIT_DELIM_CAPTURE);
    $nnc='';
    $ii=1;
    $matched=0;
    for ($j=0,$sz=sizeof($chunk);$j<$sz;++$j) {
        if (($j+1)%2) {
            $nnc.=$chunk[$j];
        } else {
            if ($nth==$ii) {
                $new='[[Rating('.$total.','.$count.')]]';
                if ($check != $chunk[$j]) break;
                $nnc.=$new;
                $matched=1;
            }
            else
                $nnc.=$chunk[$j];
            ++$ii;
        }
    }

    if (!$matched) {
        $options['title']=_("Invalid rating request !");
        $formatter->send_title('','',$options);
        $formatter->send_footer('',$options);
        return;
    }

    if (!empty($blocks)) {
        $nnc=preg_replace("/\7(\d+)\7/e",
            "\$blocks[$1]",$nnc);
    }
    $formatter->page->write($nnc);
    $DBInfo->savePage($formatter->page,"Rating",$options);

    #print "<pre>";
    #print_r($options);
    #print "</pre>";
    #print $check;   

    $options['title']=_("Rating successfully !");
    $formatter->send_title('','',$options);
    $formatter->send_page('',$options);
    $formatter->send_footer('',$options);
    return;
}

// vim:et:sts=4:
?>