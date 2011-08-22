<?php


function _compo2_preview_sort($a,$b) {
    return strcasecmp($a["title"],$b["title"]);
}

function _compo2_preview($params,$_link="?action=preview") {
    if (isset($_REQUEST["uid"])) {
        echo "<p><a href='?action=preview'>Back to View all Entries</a></p>";
        _compo2_preview_show($params,intval($_REQUEST["uid"]));
        _compo2_show_comments($params["cid"],intval($_REQUEST["uid"]));
        return;
    }

    $etype = $_REQUEST["etype"];
    $cats = array(""=>"All Entries");
    foreach ($params["divs"] as $div) {
        $cats[$div] = "{$params["{$div}_title"]} Entries";
    }
    
    $cnte = array_pop(compo2_query("select count(*) _cnt from c2_entry where etype like ? and cid = ? ".(!($params["state"]=="admin")?" and active=1":""),array("%$etype%",$params["cid"])));
    $cnt = $cnte["_cnt"];
    
    $limit = 24;
    $start = 0;
    if (isset($_REQUEST["start"])) { $start = intval($_REQUEST["start"]); }
    $start = intval($start); $limit = intval($limit);
    
    $r = compo2_query("select * from c2_entry where etype like ? and cid = ? ".(!($params["state"]=="admin")?" and active=1":"")." limit $start,$limit",array("%$etype%",$params["cid"]));
    usort($r,"_compo2_preview_sort");

    echo "<h3>".htmlentities($cats[$etype])." ($cnt)</h3>";
    
//     if ($params["gamejam"]) {
    if (count($params["divs"]) > 1) {
        echo "<p>"; $pre = "";
        foreach ($cats as $kk=>$vv) {
            echo "$pre<a href='?action=preview&etype=$kk'>$vv</a>"; $pre = " | ";
        }
        echo "</p>";
    }
    
    echo "<p>[ ";
    $n=1;
    for ($i=0; $i<$cnt; $i+=$limit) {
        if ($i == $start) { echo "$n "; continue; }
        echo "<a href='?action=preview&etype=".urlencode($etype)."&start=$i'>$n</a> ";
        $n += 1;
    }
    echo " ]</p>";

    $ce = compo2_entry_load($params["cid"],$params["uid"]);
    if ($ce["id"]) { echo "<p><a href='?action=edit'>Edit your entry.</a></p>"; }

    $cols = 6;
    $n = 0;
    $row = 0;
    echo "<table class='preview'>";
    foreach ($r as $e) {
        if (($n%$cols)==0) { echo "<tr>"; $row += 1; } $n += 1;
        $klass = "class='alt-".(1+(($row)%2))."'";
        
/*        $etype = htmlentities($e["etype"]);
        $klass = "class='alt-".($etype=="compo"?"1":"2")."'";*/
        
        echo "<td valign=bottom align=center $klass>";
        $link = "$_link&uid={$e["uid"]}";
        echo "<div><a href='$link'>";
        $shots = unserialize($e["shots"]);
        echo "<img src='".compo2_thumb($shots["shot0"],120,90)."'>";
        echo "<div class='title'><i>".htmlentities($e["title"])."</i></div>";
        echo compo2_get_user($e["uid"])->display_name;
        echo "</a></div>";
        if ($e["disabled"]) { echo "<div><i>disabled</i></div>"; }
        else { if (!$e["active"]) { echo "<div><i>inactive</i></div>"; } }
    }
    echo "</table>";

    $ce = compo2_entry_load($params["cid"],$params["uid"]);
    if ($ce["id"]) { echo "<p><a href='?action=edit'>Edit your entry.</a></p>"; }
    echo "<p><a href='?action=misc_links'>See all links</a></p>";

}

function compo2_strip($v) {
    return stripslashes($v);
}

function _compo2_preview_show_links($ce) {
    $pre = "";
    foreach (unserialize($ce["links"]) as $le) {
        if (!strlen($le["title"])) { continue; }
        $link = $le["link"];
        if (strpos($link,"javascript:") === 0) { continue; }
        if (strpos($link,"?") === 0) { continue; }
        if (!preg_match("/^\w+\:\/\//",$link)) { continue; }
        echo "$pre<a href=\"".htmlentities($link)."\" target='_blank'>".htmlentities($le["title"])."</a>";
        $pre = " | ";
    }
}

function get_gravatar( $email, $s = 80, $d = 'mm', $r = 'g', $img = false, $atts = array() ) {
    $url = 'http://www.gravatar.com/avatar/';
    $url .= md5( strtolower( trim( $email ) ) );
    $url .= "?s=$s&d=$d&r=$r";
    if ( $img ) {
        $url = '<img src="' . $url . '"';
        foreach ( $atts as $key => $val )
            $url .= ' ' . $key . '="' . $val . '"';
        $url .= ' />';
    }
    return $url;
}


function _compo2_preview_comments($params,$uid,$form=true) {
    if ($form) {
        if ($params["uid"]) {
            $comments = trim(compo2_strip($_REQUEST["comments"]));
            if (strlen($comments)) {
                compo2_insert("c2_comments",array(
                    "cid"=>$params["cid"],
                    "to_uid"=>$uid,
                    "from_uid"=>$params["uid"],
                    "ts"=>date("Y-m-d H:i:s"),
                    "content"=>$comments,
                ));
                header("Location: ?action=preview&uid=$uid"); die;
            }
        }
    }
            
    $r = compo2_query("select * from c2_comments where cid = ? and to_uid = ? order by ts asc",array($params["cid"],$uid));
    
    echo "<h3>Comments</h3>";
    $pe = array();
    foreach ($r as $e) if (strlen(trim($e["content"]))) {
        // get rid of double posts.
        if (strcmp($e["from_uid"],$pe["from_uid"])==0 &&
            strcmp($e["content"],$pe["content"])==0) { continue; }
        $pe = $e;
        $user = compo2_get_user($e["from_uid"]);
        echo "<div class = 'comment'>";
        echo get_gravatar($user->user_email,48,'mm','g',true,array("align"=>"right","class"=>"gravatar"));
        echo "<div><strong>{$user->display_name} says ...</strong></div>";
        echo "<div><small>".date("M j, Y @ g:ia",strtotime($e["ts"]))."</small></div>";
        echo "<p>".str_replace("\n","<br/>",htmlentities(trim($e["content"])))."</p>";
        echo "</div>";
    }
    if ($form) {
        if ($params["uid"]) {
            echo "<form method='post' action='?action=preview&uid=$uid'>";
            echo "<textarea name='comments' rows=4 cols=60></textarea>";
            echo "<p><input type='submit' value='Submit Comment'></p>";
        } else {
            echo "<p>You must sign in to comment.</p>";
        }
    }
}
        

function _compo2_preview_show($params,$uid,$comments=true) {
    $ce = compo2_entry_load($params["cid"],$uid);
    $user = compo2_get_user($ce["uid"]);
    
    echo "<h3>".htmlentities($ce["title"])." - {$user->display_name}";
    $div = $ce["etype"];
    echo " - <i>{$params["{$div}_title"]} Entry</i>";
    echo "</h3>";
    
    echo "<p class='links'>";
    _compo2_preview_show_links($ce);
    echo "</p>";
    
    echo "<p>".str_replace("\n","<br/>",htmlentities($ce["notes"]))."</p>";
    
    $shots = unserialize($ce["shots"]);
    $fname = array_shift($shots);
        
    
    echo "<table>";
    $cols = 4; $n = 0;
    $link = get_bloginfo("url")."/wp-content/compo2/$fname";
    echo "<tr><td colspan=$cols align=center><a href='$link' target='_blank'><img src='".compo2_thumb($fname,450,450)."'></a>";
    foreach ($shots as $fname) {
        if (($n%$cols)==0) { echo "<tr>"; } $n += 1;
        $link = get_bloginfo("url")."/wp-content/compo2/$fname";
        echo "<td><a href='$link' target='_blank'><img src='".compo2_thumb($fname,120,120)."'></a>";
    }
    echo "</table>";
    
    if ($params["jcat"]) {
        $link = get_bloginfo("url")."/?category_name={$params["jcat"]}&author_name={$user->user_nicename}";
        echo "<p><a href='$link' target='_blank'>View {$user->display_name}'s journal.</a></p>";
    }
    
    if ($params["state"] == "results" || $params["state"] == "admin") {
        _compo2_results_ratings($params,$uid);
    }
    
    if ($comments) {
        _compo2_preview_comments($params,$uid,true);
    }
}

?>