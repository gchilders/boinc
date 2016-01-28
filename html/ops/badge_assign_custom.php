#!/usr/bin/env php
<?php
// This file is part of BOINC.
// http://boinc.berkeley.edu
// Copyright (C) 2013 University of California
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

// Assign badges based on RAC percentile.
// Customize this to grant other types of badges

require_once("../inc/util_ops.inc");

// thresholds for the various badges
// (i.e. gold badge is for top 1% of active users/teams)
//
$badge_pctiles = array(1, 5, 25);
$badge_images = array("pct_1.png", "pct_5.png", "pct_25.png");
$badge_levels = array(10000, 100000, 500000, 1000000, 5000000, 10000000, 50000000, 100000000, 500000000);
$badge_level_names = array("10k", "100k", "500k", "1m", "5m", "10m", "50m", "100m", "500m");
$badge_images_14e = array("bronze_14e.png", "silver_14e.png", "gold_14e.png", "amethyst_14e.png", "turquoise_14e.png", "sapphire_14e.png", "ruby_14e.png", "emerald_14e.png", "diamond_14e.png");
$badge_images_15e = array("bronze_15e.png", "silver_15e.png", "gold_15e.png", "amethyst_15e.png", "turquoise_15e.png", "sapphire_15e.png", "ruby_15e.png", "emerald_15e.png", "diamond_15e.png");
$badge_images_16e = array("bronze_16e.png", "silver_16e.png", "gold_16e.png", "amethyst_16e.png", "turquoise_16e.png", "sapphire_16e.png", "ruby_16e.png", "emerald_16e.png", "diamond_16e.png");
$badge_images_nfs = array("bronze_nfs.png", "silver_nfs.png", "gold_nfs.png", "amethyst_nfs.png", "turquoise_nfs.png", "sapphire_nfs.png", "ruby_nfs.png", "emerald_nfs.png", "diamond_nfs.png");

// get the records for percentile badges; create them if needed
//
function get_pct_badges($badge_name_prefix, $badge_pctiles, $badge_images) {
    $badges = array();
    for ($i=0; $i<3; $i++) {
        $badges[$i] = get_badge($badge_name_prefix."_".$i, "Top ".$badge_pctiles[$i]."% in average credit", $badge_images[$i]);
    }
    return $badges;
}

// badge_name_prefix should be user
// app_name should be 14e, 15e, 16e, or nfs

function get_nfs_badges($badge_name_prefix, $badge_level_names, $badge_images, $app_name) {
    $badges = array();
    $limit = count($badge_level_names);
    for ($i=0; $i < $limit; $i++) {
        $badges[$i] = get_badge($badge_name_prefix."_".$app_name."_".$i, "$badge_level_names[$i] in ".$app_name." credit", $badge_images[$i]);
        // $badges[$i] = get_badge($badge_name_prefix."_".$sub_project["short_name"]."_".$i, "$badge_level_names[$i] in ".$sub_project["name"]." credit", $sub_project["short_name"].$badge_images[$i]);
	// echo "badge level $badge_level_names[$i]\n";
    }
    return $badges;
}

// get the RAC percentiles from the database
//
function get_percentiles($is_user, $badge_pctiles) {
    $percentiles = array();
    for ($i=0; $i<3; $i++) {
        if ($is_user) {
            $percentiles[$i] = BoincUser::percentile("expavg_credit", "expavg_credit>1", 100-$badge_pctiles[$i]);
        } else {
            $percentiles[$i] = BoincTeam::percentile("expavg_credit", "expavg_credit>1", 100-$badge_pctiles[$i]);
        }
        if ($percentiles[$i] === false) {
            die("Can't get percentiles\n");
        }
    }
    return $percentiles;
}

// decide which badge to assign, if any.
// Unassign other badges.
//
function assign_pct_badge($is_user, $item, $percentiles, $badges) {
    for ($i=0; $i<3; $i++) {
        if ($item->expavg_credit >= $percentiles[$i]) {
            assign_badge($is_user, $item, $badges[$i]);
            unassign_badges($is_user, $item, $badges, $i);
            return;
        }
    }
    unassign_badges($is_user, $item, $badges, -1);
}

function assign_nfs_badge($is_user, $item, $levels, $badges) {
    for ($i=8; $i>=0; $i--) {
        if ($item->total_credit >= $levels[$i]) {
            assign_badge($is_user, $item, $badges[$i]);
            unassign_badges($is_user, $item, $badges, $i);
            return;
        }
    }
    unassign_badges($is_user, $item, $badges, -1);
}

function assign_nfs_app_badge($is_user, $item, $levels, $badges, $where_clause) {
    if ($is_user) {
	$query = _mysql_query("select sum(total) from credit_user where userid=".$item->id." and ($where_clause)");
    } else {
	$query = _mysql_query("select sum(total) from credit_team where teamid=".$item->id." and ($where_clause)");
    }
    $x = mysqli_fetch_array($query);
    _mysql_free_result($query);

    for ($i=8; $i>=0; $i--) {
        if ($x[0] >= $levels[$i]) {
            assign_badge($is_user, $item, $badges[$i]);
            unassign_badges($is_user, $item, $badges, $i);
            return;
        }
    }
    unassign_badges($is_user, $item, $badges, -1);
}


// Scan through all the users/teams, 1000 at a time,
// and assign/unassign RAC badges
//
function assign_badges($is_user, $badge_pctiles, $badge_images) {
    $kind = $is_user?"user":"team";
    $badges = get_pct_badges($kind."_pct", $badge_pctiles, $badge_images);
    $pctiles = get_percentiles($is_user, $badge_pctiles);
    echo "thresholds for $kind badges: $pctiles[0] $pctiles[1] $pctiles[2]\n";

    $n = 0;
    $maxid = $is_user?BoincUser::max("id"):BoincTeam::max("id");
    while ($n <= $maxid) {
        $m = $n + 1000;
        if ($is_user) {
            $items = BoincUser::enum_fields("id, expavg_credit", "id>=$n and id<$m and total_credit>0");
        } else {
            $items = BoincTeam::enum_fields("id, expavg_credit", "id>=$n and id<$m and total_credit>0");
        }
        foreach ($items as $item) {
            assign_pct_badge($is_user, $item, $pctiles, $badges);
            // ... assign other types of badges
        }
        $n = $m;
    }
}

function assign_nfs_badges($is_user, $badge_levels, $badge_level_names, $badge_images) {
    $kind = $is_user?"user":"team";
    $badges = get_nfs_badges($kind, $badge_level_names, $badge_images, "nfs");

    $n = 0;
    $maxid = $is_user?BoincUser::max("id"):BoincTeam::max("id");
    while ($n <= $maxid) {
        $m = $n + 1000;
        if ($is_user) {
            $items = BoincUser::enum_fields("id, total_credit", "id>=$n and id<$m and total_credit>0");
        } else {
            $items = BoincTeam::enum_fields("id, total_credit", "id>=$n and id<$m and total_credit>0");
        }
        foreach ($items as $item) {
            assign_nfs_badge($is_user, $item, $badge_levels, $badges);
            // ... assign other types of badges
        }
        $n = $m;
    }
}

function assign_nfs_app_badges($is_user, $badge_levels, $badge_level_names, $badge_images, $app_name, $where_clause) {
    $kind = $is_user?"user":"team";
    $badges = get_nfs_badges($kind, $badge_level_names, $badge_images, $app_name);

    $n = 0;
    $maxid = $is_user?BoincUser::max("id"):BoincTeam::max("id");
    while ($n <= $maxid) {
        $m = $n + 1000;
        if ($is_user) {
            $items = BoincUser::enum_fields("id, total_credit", "id>=$n and id<$m and total_credit>0");
        } else {
            $items = BoincTeam::enum_fields("id, total_credit", "id>=$n and id<$m and total_credit>0");
        }
        foreach ($items as $item) {
            assign_nfs_app_badge($is_user, $item, $badge_levels, $badges, $where_clause);
            // ... assign other types of badges
        }
        $n = $m;
    }
}

db_init();
assign_badges(true, $badge_pctiles, $badge_images);
assign_badges(false, $badge_pctiles, $badge_images);
assign_nfs_badges(true, $badge_levels, $badge_level_names, $badge_images_nfs);
assign_nfs_app_badges(true, $badge_levels, $badge_level_names, $badge_images_14e, "14e", "appid=6");
assign_nfs_app_badges(true, $badge_levels, $badge_level_names, $badge_images_15e, "15e", "appid=7");
assign_nfs_app_badges(true, $badge_levels, $badge_level_names, $badge_images_16e, "16e", "appid=8 or appid=9");
assign_nfs_badges(false, $badge_levels, $badge_level_names, $badge_images_nfs);
assign_nfs_app_badges(false, $badge_levels, $badge_level_names, $badge_images_14e, "14e", "appid=6");
assign_nfs_app_badges(false, $badge_levels, $badge_level_names, $badge_images_15e, "15e", "appid=7");
assign_nfs_app_badges(false, $badge_levels, $badge_level_names, $badge_images_16e, "16e", "appid=8 or appid=9");

?>
