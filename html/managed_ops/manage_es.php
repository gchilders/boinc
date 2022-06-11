<?php
// This file is part of BOINC RSALS.
// http://boinc.berkeley.edu
// Copyright (C) 2008 University of California
//
// BOINC is free software; you can redistribute it and/or modify it
// under the terms of the GNU Lesser General Public License
// as published by the Free Software Foundation,
// either version 3 of the License, or (at your option) any later version.
//
// BOINC is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
// See the GNU Lesser General Public License for more details.
//
// You should have received a copy of the GNU Lesser General Public License
// along with BOINC.  If not, see <http://www.gnu.org/licenses/>.

/*
 * 2011-10-16: squalyl: fixups after boinc server update
 */

$states = array("QUEUED for SIEVING", "SIEVING", "QUEUED for POSTPROCESSING", "POSTPROCESSING", "COMPLETED");
$self=$_SERVER['PHP_SELF'];
$commands="";

//cette fonction dessine une table de paramtres pour un nombre vivant
function calc_table($id,$item) {
    $v=$item->status;
    if($v==0) {
        $editable=true;
    } else {
        $editable=false;
    }

    echo "<table>";
    echo "<tr><th>Param</th><th>Value</th>";

     $field="value_".$id;
     $v=$item->value;
     if (FALSE) {  // if($editable) {
    	echo "<tr><td>Value</td><TD align='left'>";
        echo "<input type='text' size='100' name='$field' value='$v'></TD>";
	echo "</tr>\n";
    }

    $field="polyfile_".$id;
    $v=$item->polyfile;
    echo "<tr><td>.poly file</td><TD align='left'>";
    if($editable) {
        echo "<textarea rows='15' cols='160' name='$field' wrap='off'>$v</textarea></TD></tr>\n";
    } else {
        echo "<textarea rows='15' cols='160' name='$field' wrap='off'  readonly='readonly'>$v</textarea>\n";
        // echo "<pre>".$v."</pre>";
    }

    if($editable) {
    	$field="q_start_".$id;
	$v=$item->q_start;
	echo "<tr><td>Starting Q</td><TD align='left'>";
        echo "<input type='text' size='20' name='$field' value='$v'></TD></tr>\n";

	$field="q_last_".$id;
	$v=$item->q_last;
	echo "<tr><td>Last generated Q</td><TD align='left'>";
	echo "<input type='text' size='20' name='$field' value='$v'></TD></tr>\n";
    } else {
	$range = "$item->q_start - $item->q_last";
	if($item->q_end != 0 && $item->q_start!=0) {
		$total = ($item->q_end - $item->q_start) / 4000; //should be Q_PER_WU
		$done = ($item->q_last - $item->q_start) / 4000;
		$percent = $done / $total;
		$percent = (int)($percent*10000)/100;
		$percent = "($percent %)";
	} else {
		$percent ="";
	}
	echo "<tr><td>Generated Q range</td><TD align='left'>$range $percent</td></tr>";
        if ($item->results_received > 9) {
                if ($item->primebits == 30) { $needed_rels = 100000000*1.1; }
                else if ($item->primebits == 31) { $needed_rels = 220000000*1.1; }
                else if ($item->primebits == 32) { $needed_rels = 420000000*1.1; }
                else if ($item->primebits == 33) { $needed_rels = 700000000*1.1; }
                else { $needed_rels = 10000000; }
                $rec_last = $needed_rels / $item->globresult_relations * $item->results_received * 4000 + $item->q_start; 
                $rec_last = round($rec_last/1000000);
                // if ($rec_last % 2) $rec_last += 1;
                echo "<tr><td>Recommended ending Q&nbsp;&nbsp;</td><TD align='left'>$rec_last M</td></tr>";
        }
    }

    $field="q_end_".$id;
    $v=$item->q_end;
    echo "<tr><td>Ending Q</td><TD align='left'><input type='text' size='20' name='$field' value='$v'></TD></tr>\n";

//update button
    echo "<tr><td colspan='2'><input type='submit' name='update' value='Update'></td></tr>";

    echo "</table>";
}

//cette fonction dessine une table pour un ensemble de nombres vivant ou archivs dans le mme tat
function display_result($result,$details,$comp=false) {
	global $self;
	//je ne sais plus  quoi servent ces deux
	$f1="";
	$f2="";
    if(!$details) {
        start_table("align='center'");
        echo "<TR><TH>ID</TH>
          <TH>Creation<br>Time</TH>
          <TH>Name</TH>
          <TH>Display name</TH>
          <TH>Status</TH>
          <TH>Project</TH>
          <TH>Type</TH>
          <TH>Difficulty</TH>
          <TH>Prime bits</TH>
          <TH>Post Processor</TH>
          <TH>Pastebin</TH>
          <TH>DELETE?<sup>*</sup></TH>
	  <th>Update</th>
           </TR>\n";
    }
//          <TH>Ranges</TH>


    $Nrow=_mysql_num_rows($result);
    for($j=1;$j<=$Nrow;$j++){

echo "<form action='$self' method='POST'>\n";
        if($details) {
            start_table("align='center'");
            echo "<TR><TH>ID</TH>
            <TH>Creation<br>Time</TH>
            <TH>Name</TH>
            <TH>Display name</TH>
            <TH>Status</TH>
            <TH>Project</TH>
            <TH>Type</TH>
            <TH>Difficulty</TH>
            <TH>Prime bits</TH>
            <TH>Post Processor</TH>
            <TH>Pastebin</TH>
            <TH>DELETE?<sup>*</sup></TH>
            </TR>\n";
        }  //            <TH>Ranges</TH>

        $item=_mysql_fetch_object($result);
        $id=$item->id;

    
        echo "<tr> ";
        echo "  <TD align='center'>$f1 #$id $f2</TD>\n";
    
        $time=$item->create_time;
        echo "  <TD align='center'>$f1 " .time_str($time)." $f2</TD>\n";
    
        //echo "  <TD align='center'>$f1 " .$item->name." $f2</TD>\n";
        $filedel = "/home/boincadm/projects/nfs/sample_results/managed/".$item->name;
        if ($comp and file_exists($filedel)) {
            echo "  <TD align='center'> <b>$f1 " .$item->name." $f2</b></TD>\n";
        } else {
            echo "  <TD align='center'>$f1 " .$item->name." $f2</TD>\n";
        }

        $field="dispname_".$id;
        $v=$item->dispname;
        echo "  <TD align='center'>
        <input type='text' size='40' name='$field' value='$v'></TD>\n";
        
		$field="status_$id";
        echo "  <TD align='center'><select name='$field'>";
		global $states;
		for($i=0;$i<count($states);$i++) {
			if($i==$item->status) {
				$sel=" selected='true'";
			} else {
				$sel="";
			}
			echo "<option value='$i'$sel>".$states[$i]."</option>";
		}
		echo "</select></TD>\n";
    
        $field="project_".$id;
        $v=$item->project;
        echo "  <TD align='center'><input type='text' size='20' name='$field' value='$v'></TD>\n";
    
        $field="type_".$id;
        $v=$item->type;
        echo "  <TD align='center'><input type='text' size='10' name='$field' value='$v'></TD>\n";
    
        $field="difficulty_".$id;
        $v=$item->difficulty;
        echo "  <TD align='center'><input type='text' size='10' name='$field' value='$v'></TD>\n";
    
        $field="primebits_".$id;
        $v=$item->primebits;
        echo "  <TD align='center'><input type='text' size='8' name='$field' value='$v'></TD>\n";
    
        $field="postprocessor_".$id;
        $v=$item->postprocessor;
        echo "  <TD align='center'><input type='text' size='60' name='$field' value='$v'></TD>\n";

        $field="pastebin_".$id;
        $v=$item->pastebin;
        echo "  <TD align='center'><input type='text' size='10' name='$field' value='$v'></TD>\n";
        
        $field="delete_number_".$id; 
        echo "  <TD align='center'><input type='text' size='6' name='$field' value=''></TD>\n";
    
        $field="ranges_".$id;
        $v=$item->ranges;
        // echo "  <TD align='center'><input type='text' size='30' name='$field' value='$v'></td>\n";
		if(!$details) {
			//add submit button for each number
			echo "<td><input type='submit' value='Update' /></td>\n";
		}
    
        echo "</tr> ";

//table pour les parametres de calcul
		if($details) {
			echo "<tr><td colspan='11'>";
        	calc_table($id,$item);
	        echo "</td></tr>";
		}
    	if($details) {
        	end_table();
        }

echo "</form>\n";

    } //end number loop
    if(!$details) {
        end_table();
    }
} 

/***********************************************************************\
 *  Display and Manage BOINC managed numbers
 * 
 * This page lists the numbers currently sieved by the project and
 * displays status for each of them.
 *
 * Sebastien Lorquet <squalyl@gmail.com>  - 18 March 2010
 * @(#) $Id$
\***********************************************************************/

$skip_auth_ops=1;
require_once('../inc/util_ops.inc');

db_init();
error_reporting(E_ERROR | E_WARNING | E_PARSE);

// Platform and application labels (are better than numbers)

$result = _mysql_query("SELECT * FROM number_es");
$Nnumber =  _mysql_num_rows($result);
for($i=0;$i<$Nnumber;$i++){
    $item=_mysql_fetch_object($result);
    $id=$item->id;
	//la valeur deprecated n'existe plus et n'est pas utilise
    $plat_off[$id]=false;
    $platform[$id]=$item->name;
 }
_mysql_free_result($result);


/***************************************************\
 *  Action: process form input for changes
 \***************************************************/

function update_if_changed($form, $id, $old_v, $sql_field, $check_larger=false, $compare=null) {
	$field = $form."_".$id;
        $commands = "";
        if(!isset($_POST[$field])) return '';
        $new_v= _mysql_escape_string($_POST[$field]);
		if($check_larger) {
		if($new_v == '') { // debrouxl: swallow empty values so that they don't generate warnings
			return '';
		}
		if((int)$new_v < (int)$compare) {
			$commands = "<p><pre style='text-color:red;'>WARNING: new value ($new_v) must be bigger than $compare</pre></p>";
			return $commands;
		}
	}

    if( $new_v != $old_v && $new_v!='') {
        $cmd =  "UPDATE number_es SET $sql_field='$new_v' WHERE id=$id";
        $commands = "<P><pre>$cmd</pre>\n";
        _mysql_query("SET SQL_MODE = ''");
        _mysql_query($cmd);
    }
	return $commands;
}

if( !empty($_POST) ) {

	/* Changing properties of existing numbers */

	$result = _mysql_query("SELECT * FROM number_es");
	$Nrow=_mysql_num_rows($result);

	for($j=1;$j<=$Nrow;$j++){  // test/update each row in DB
		$item=_mysql_fetch_object($result);
		$id=$item->id;

        /* Delete this entry? */
        $field="delete_number_".$id; 
        if( isset($_POST[$field]) && $_POST[$field]=='DELETE' ) {
            $cmd =  "DELETE FROM number_es WHERE id=$id";
            $commands .= "<P><pre>$cmd</pre>\n";
           _mysql_query("SET SQL_MODE = ''");
           _mysql_query($cmd);
            continue;  // next row, this number is gone
        }

		$commands .= 	update_if_changed("dispname",		$id, $item->dispname		, "dispname"	);
		$commands .= 	update_if_changed("project",		$id, $item->project			, "project"		);
		$commands .= 	update_if_changed("type",			$id, $item->type			, "type"		);
		$commands .= 	update_if_changed("status",			$id, $item->status			, "status"	, false, $item->status); //new > old! - remove check

		$commands .= 	update_if_changed("difficulty",		$id, $item->difficulty		, "difficulty"		);
		$commands .= 	update_if_changed("primebits",		$id, (int)$item->primebits	, "primebits"		);
		$commands .= 	update_if_changed("postprocessor",	$id, $item->postprocessor	, "postprocessor"	);
		$commands .= 	update_if_changed("pastebin",		$id, $item->pastebin	, "pastebin"	);
		$commands .= 	update_if_changed("ranges",			$id, $item->ranges			, "ranges"		);
		if($item->status==0) {
			$commands .= update_if_changed("value",			$id, $item->value			, "value"		);
			$old       = _mysql_escape_string($item->polyfile);
			$commands .= update_if_changed("polyfile",		$id, $old					, "polyfile"		);
			$commands .= update_if_changed("q_start",		$id, (int)$item->q_start	, "q_start"		);
			$commands .= update_if_changed("q_last",		$id, (int)$item->q_last		, "q_last"		);
		}
		$commands .= update_if_changed("q_end",				$id, (int)$item->q_end		, "q_end"	,true, $item->q_last ); //new > old!

    } //for each number

    /* Adding a new number */

    if( isset($_POST['add_number']) && $_POST['add_number'] ) {
        $name= _mysql_escape_string($_POST['add_name']);
        $project=_mysql_escape_string($_POST['add_project']);
        if( empty($name) || empty($project) ) {
            $commands .= "<p><font color='red'>To add a new number please supply both a name and a project.</font></p>\n";
        }
        else {
            $now=time();
            $cmd =  "INSERT INTO number_es (name,create_time,project) ".
                "VALUES ('$name',$now,'$project')";
            $commands .= "<P><pre>$cmd</pre>\n";
            _mysql_query("SET SQL_MODE = ''");
            _mysql_query($cmd);
        }
    }
 }//$_POST


/***************************************************\
 * Display the DB contents in a form
 \***************************************************/

admin_page_head("Manage lasievee_small Project Numbers");

echo "<h2>ATTENTION! For security, only change one number at a time!</h1>";
echo "<h3>Make changes in the input fields then click on 'update' to make changes to a number.</h3>";
echo "<h3>Empty fields are ignored. Use a space to clear a field.</h3>";
// echo "<h3><a href=\"http://escatter11.fullerton.edu/nfs_data\">Data directory</a></h3>";
echo $commands;

echo"<P>
     <h2>QUEUED FOR SIEVING</h2>\n";

$q="SELECT * FROM number_es WHERE status=0 ORDER BY id";
$result = _mysql_query($q);
if(_mysql_num_rows($result)>0) {
	display_result($result,true);
} else {
	echo "none\n";
}
_mysql_free_result($result);

echo"<P><hr><hr>
     <h2>SIEVING</h2>\n";

$q="SELECT * FROM number_es WHERE status=1 ORDER BY id";
$result = _mysql_query($q);
if(_mysql_num_rows($result)>0) {
	display_result($result,true);
} else {
	echo "none\n";
}
_mysql_free_result($result);


echo"<P><hr><hr>
     <h2>QUEUED FOR POSTPROCESSING</h2>\n";

$q="SELECT * FROM number_es WHERE status=2 ORDER BY id";
$result = _mysql_query($q);
if(_mysql_num_rows($result)>0) {
	display_result($result,false);
} else {
	echo "none\n";
}
_mysql_free_result($result);
// echo "<input type='submit' name='update' value='Update'>";


echo"<P><hr><hr>
     <h2>POSTPROCESSING</h2>\n";

$q="SELECT * FROM number_es WHERE status=3 ORDER BY id";
$result = _mysql_query($q);
if(_mysql_num_rows($result)>0) {
	display_result($result,false);
} else {
	echo "none\n";
}
_mysql_free_result($result);
// echo "<input type='submit' name='update' value='Update'>";


echo"<P><hr><hr>
     <h2>COMPLETED</h2>\n";

$q="SELECT * FROM number_es WHERE status=4 ORDER BY id";
$result = _mysql_query($q);
if(_mysql_num_rows($result)>0) {
	display_result($result,false,true);
} else {
	echo "none\n";
}
_mysql_free_result($result);

// echo "<input type='submit' name='update' value='Update'><br>
echo "<br><sup>*</sup>To delete an entry you must enter the word 'DELETE' in this field, in all capital letters.<br>\n";



/**
 * Entry form to create a new number 
 */

echo"<P><hr><hr>
     <h2>Add a Number</h2>
  To add a number to the project enter the name and project below.  </p>\n";

start_table("align='center' ");

echo "<TR><TH>Name</TH>
          <TH>Project</TH>
          <TH> &nbsp;   </TH>
      </TR>\n";

//20101027-squalyl: added missing form tag... oops!
echo "<form action='$self' method='POST'>\n";
echo "<TR>
        <TD> <input type='text' size='20' name='add_name' value=''></TD>
        <TD> <input type='text' size='20' name='add_project' value=''></TD>
        <TD align='center' bgcolor='#FFFF88'>
             <input type='submit' name='add_number' value='Add Number'></TD>
        </TR>\n";
echo "</form>\n";

end_table();

echo "</body></html>\n";
// admin_page_tail();

?>
