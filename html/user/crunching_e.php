<?php
// This file is part of BOINC.
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
//    	    echo "	<td>".time_str($number->create_time)."</td>";

/*
changelog
	squalyl  2010 apr 26 	- display details for numbers queued for post processing
				- removed XML bits
	squalyl  2010 apr 27	- added the "estimated pending relations" column
	debrouxl 2010 apr 27	- changed the "Now crunching" comment and the "~ Pending Rels" title 
	squalyl	 2010 apr 30	- avoided div/0 when received_relations was zero and computing pending relations
	debrouxl 2010 may 08	- slightly reduced the indication on the minimum numbers of relations 
				  (223251500 relations on 10003_250 showed significant oversieving)
	squalyl  2011 aug 26    - add indication of order, to allow counting number per category
*/

function display_result($xml, $numbers, $type, $details=false) {
	start_table();
	echo "<tr>";
	echo "<th>#</th>";
	echo "<th>".tra("Name")."</th>";
	echo "<th>".tra("Project")."</th>";
	echo "<th>".tra("Type")."</th>";
	echo "<th>".tra("Bits")."</th>";
	if($details) {
		echo "<th>".tra("Q range")."</th>";
		echo "<th>".tra("Pushed")."</th>";
		echo "<th>".tra("Unsent")."</th>";
		echo "<th>".tra("Pending")."</th>";
		echo "<th>".tra("Received")."</th>";
		echo "<th>".tra("Relations")."</th>";
		echo "<th>".tra("Est. Pending Rels")."</th>";
	}
	echo "<th>".tra("Post processing / Comments")."</th>";
	echo "</tr>";

	$order=1;

	foreach ($numbers as $number) {
		echo "<tr>";
		echo "	<td>$order</td>";
		$order++;

		echo "	<td>$number->dispname</td>";
		echo "<td align='center'>$number->project</td>";
		echo "<td align='center'>$number->type($number->difficulty)</td>";
		echo "<td align='center'>$number->primebits</td>";
		if($details) {
			if($number->q_end != 0 && $number->q_start != 0) {
				$last = $number->q_last;
                                if ($last == 0) $last = $number->q_start;
                                $ratio = ($last - $number->q_start) / ($number->q_end - $number->q_start);
				$ratio = (int)($ratio*10000)/100;
				$ratio = "$ratio %";
				$range = ($number->q_start/1000000)."-".($number->q_end/1000000)." M";
				$u     = $number->results_unsent;
				$p     = $number->results_pending;
				$r     = $number->results_received;
				$rels  = $number->globresult_relations;
				$numwu = ($number->q_end - $number->q_start)/4000;
				$numunc = ($number->q_end - $number->q_last)/4000;
				if($number->results_received==0) {
					$prels="...";
				} else {
					$prels  = number_format((int)($rels / $r * ($numwu - $r - $u - $numunc)));
				}
				$rels = number_format($rels);
			} else {
				$range = "(manual)";
				$ratio = "--";
				$u     = "--";
				$p     = "--";
				$r     = "--";
				$rels  = "--";
				$prels = "--";
			}
			echo "<td align='right'>$range</td>";
			echo "<td align='right'>$ratio</td>";
			echo "<td align='right'>$u</td>";
			echo "<td align='right'>$p</td>";
			echo "<td align='right'>$r</td>";
			echo "<td align='right'>$rels</td>";
			echo "<td align='right'>$prels</td>";
		}
		if (!empty($number->pastebin)) {
			echo "<td><a href=\"http://pastebin.com/$number->pastebin\">$number->postprocessor</a></td>";
		} else {
			echo "<td>$number->postprocessor</td>";
		}
		echo "</tr>";
	}    
	end_table();
}


require_once("../inc/boinc_db.inc");
require_once("../inc/boinc_db_rsals.inc");
require_once("../inc/util.inc");
require_once("../inc/translation.inc");
db_init();

$xml = get_str('xml', true);
if($xml) {
	die( "xml not supported" );
}

page_head(tra("Detailed status of lasievee"));
echo tra("%1 currently is sieving the following numbers using the lasievee app. When you participate in %1, work for one or more of these numbers will be assigned to your computer. The parameters defining the number will be downloaded to your computer, and the computed 'relations' needed for the post processing step will be uploaded to the main server. This happens automatically; you don't have to do anything. See the bottom of the page for the meaning of each column.", PROJECT)."<br>";



echo "<h2>Now sieving</h2>";
echo "<p>These numbers are being distributed to the clients right now. You can notice them because the workunits you get starts with these names.</p>";
// We need ~55-60M relations for 29-bits numbers, 110+M for 30 bits numbers, and 210+M for 31 bits numbers.

$numbers = BoincNumber_e::enum("status=1");
display_result($xml,$numbers,"working",true);
$res = _mysql_query("SELECT SUM(globresult_size) AS size FROM number_e WHERE status=1");
if($res!==false && _mysql_num_rows($res)==1) {
	$obj=_mysql_fetch_object($res);
	$totalbytes=((int)($obj->size/10000000))/100;
	_mysql_free_result($res);
}
echo "<p>Total size of result files: $totalbytes GB</p>";

echo "<h2>Queued</h2>";
echo "<p>These numbers are planned, they are currently reviewed or waiting for space on the server disk, and will soon be processed.</p>";
$numbers = BoincNumber_e::enum("status=0");
display_result($xml,$numbers,"queued");

echo "<h2>Queued for post processing</h2>";
echo "<p>These numbers have been successfully sieved, and are waiting for someone to post process them. We're still receiving pending results, but no work is generated because we have sufficient relations.</p>";
$numbers = BoincNumber_e::enum("status=2");
display_result($xml,$numbers,"pp_queued",true);
$res = _mysql_query("SELECT SUM(globresult_size) AS size FROM number_e WHERE status=2");
if($res!==false && _mysql_num_rows($res)==1) {
	$obj=_mysql_fetch_object($res);
	$totalbytes=((int)($obj->size/10000000))/100;
	_mysql_free_result($res);
}
echo "<p>Total size of MANAGED pending files: $totalbytes GB</p>";

echo "<h2>Post processing</h2>";
echo "<p>These numbers have been successfully sieved, and are being post processed</p>";
$numbers = BoincNumber_e::enum("status=3");
display_result($xml,$numbers,"post_processing");

// Remove below to remove completed numbers
echo "<h2>Completed</h2>";
echo "<p>These numbers have been successfully factored</p>";
$numbers = BoincNumber_e::enum("status=4");
display_result($xml,$numbers,"done");
	
echo "<h2>Explanations</h2>";
echo "<p><strong>Name: </strong>The name identifying the number (Cnnn=composite, etc, project dependent) - probably the workunits name prefix</p>";
echo "<p><strong>Project: </strong>The factoring project for which this number is sieved</p>";
echo "<p><strong>Type: </strong>Factoring algorithm and relative calculation difficulty</p>";
echo "<p><strong>Bits: </strong>Another measure of difficulty, indicating how much relations are required</p>";
echo "<p><strong>Q range: </strong>Sieving range, indicating the total number of workunits, each one managing 4k Q values</p>";
echo "<p><strong>Pushed: </strong>Fraction of the Q range for which workunits have been generated</p>";
echo "<p><strong>Unsent: </strong>Workunits that have been generated but not sent to anyone yet</p>";
echo "<p><strong>Pending: </strong>Workunits that have been sent to clients for computation</p>";
echo "<p><strong>Received: </strong>Workunits that have been processed by clients, and that we have received and accumulated</p>";
echo "<p><strong>Relations: </strong>Cumulated number of NFS relations in all the received results</p>";
echo "<p><strong>Est. Pending Rels: </strong>Estimated number of relations in the pending result, calculated from the number of received relations and results</p>";

page_tail();
?>
