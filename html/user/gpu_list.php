<?php
// This file is part of BOINC.
// http://boinc.berkeley.edu
// Copyright (C) 2011 University of California
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
// generate a page of the best-performing GPU models.
//
// "best-performing" is defined as minimizing the average of
//
// elapsed_time(J)/rsc_fpops_est(J)
// over completed jobs J currently in the DB
//
// TODO: this is fantastically inefficient

require_once("../inc/util.inc");
require_once("../inc/common_defs.inc");

define('GPU_MIN_RESULTS', 10);
// strip leading AMD, NVIDIA, etc.
// This avoids showing the same model twice
//
function strip_vendor($model) {
    foreach (array("AMD ", "NVIDIA ", "ATI ", "Intel(R) ") as $maker) {
        $n = strlen($maker);
        if (substr($model, 0, $n) == $maker) {
            return substr($model, $n);
        }
    }
    return $model;
}
// given a host.misc field,
// extract the GPU model name for the given vendor
//
function get_gpu_model($x, $vendor) {
    if (empty($x->gpus)) return null;
    foreach ($x->gpus as $gpu) {
        if ($gpu->type == $vendor) {
            return $gpu->model;
        }
    }
    return null;
}
function add_model($model, $r, $wu, &$models) {
    if (array_key_exists($model, $models)) {
        $models[$model]->count++;
        $models[$model]->time += $r->elapsed_time/$wu->rsc_fpops_est;
    } else {
        $x = new StdClass;
        $x->count = 1;
        $x->time = $r->elapsed_time/$wu->rsc_fpops_est;
        $models[$model] = $x;
    }
}
// return a data structure containing GPU usage info for a vendor
// $x->total: combined list
// $x->windows
// $x->linux
// $x->mac
//
// vendor is nvidia or amd

function get_gpu_list($vendor) {

    // plan class names contain:
    // nvidia or cuda
    // amd or ati
    // intel
    // apple
    $clause = "plan_class like '%$vendor%'";
    if ($vendor == 'amd') {
        $clause .= " or plan_class like '%ati%'";
    }
    if ($vendor == 'nvidia') {
        $clause .= " or plan_class like '%cuda%'";
    }
    $avs = BoincAppVersion::enum($clause);
    if (count($avs) == 0) {
        $x = new StdClass;
        $x->total = array();
        $x->win = array();
        $x->linux = array();
        $x->mac = array();
        return $x;
    }
    $av_ids = [];
    foreach($avs as $av) {
        $av_ids[] = $av->id;
    }
    if ($vendor == "nvidia") {
        $av_ids[] = ANON_PLATFORM_NVIDIA;
    } else if ($vendor == "amd") {
        $av_ids[] = ANON_PLATFORM_ATI;
    }

    $av_ids = implode(',', $av_ids);
    $t = time() - 30*86400;
    //echo "start enum $vendor $av_ids\n";
    $results = BoincResult::enum(
        "app_version_id in ($av_ids) and create_time > $t and elapsed_time > 100 "
        ."and outcome = 1 and validate_state = 1 and exit_status = 0"
    );
    //echo "end enum\n";
    $total = array();
    $win = array();
    $linux = array();
    $mac = array();
    foreach ($results as $r) {
        $h = BoincHost::lookup_id($r->hostid);
        if (!$h) continue;
        $wu = BoincWorkunit::lookup_id($r->workunitid);
        if (!$wu) continue;
        $model = get_gpu_model(json_decode($h->misc), $vendor);
        if (!$model) continue;
        add_model($model, $r, $wu, $total);
        if (strstr($h->os_name, "Windows")) {
            add_model($model, $r, $wu, $win);
        }
        if (strstr($h->os_name, "Linux")) {
            add_model($model, $r, $wu, $linux);
        }
        if (strstr($h->os_name, "Darwin")) {
            add_model($model, $r, $wu, $mac);
        }
    }
    $x = new StdClass;
    $x->total = $total;
    $x->win = $win;
    $x->linux = $linux;
    $x->mac = $mac;
    return $x;
}

function merge_model_lists($a, $b) {
    $out = array();
    foreach (array($a, $b) as $models) {
        foreach ($models as $model=>$x) {
            if (!array_key_exists($model, $out)) {
                $y = new StdClass;
                $y->count = 0;
                $y->time = 0;
                $out[$model] = $y;
            }
            $out[$model]->count += $x->count;
            $out[$model]->time += $x->time;
        }
    }
    return $out;
}

function merge_gpu_lists($a, $b) {
    $x = new StdClass;
    $x->total = merge_model_lists($a->total, $b->total);
    $x->win = merge_model_lists($a->win, $b->win);
    $x->linux = merge_model_lists($a->linux, $b->linux);
    $x->mac = merge_model_lists($a->mac, $b->mac);
    return $x;
}

function get_gpu_lists() {
    $x = new StdClass;
    $x->cuda = get_gpu_list("nvidia");
    $x->ati = get_gpu_list("amd");
    $x->nvidia_amd = merge_gpu_lists($x->cuda, $x->ati);
    $x->time = time();
    return $x;
}

function gpucmp($x1, $x2) {
    return $x1->avg_time > $x2->avg_time;
}
function show_list($models, $name) {
    echo "<td><h2>$name</h2>\n";
    if (!count($models)) {
        echo tra("No GPU tasks reported")."</td>\n";
        return;
    }
    foreach ($models as $model=>$x) {
        $x->avg_time = $x->time/$x->count;
    }
    $min_time = 1e9;
    foreach ($models as $model=>$x) {
        if ($x->count < GPU_MIN_RESULTS) continue;
        if ($x->avg_time < $min_time) $min_time = $x->avg_time;
    }
    uasort($models, 'gpucmp');
    echo "<ol>\n";
    foreach ($models as $model=>$x) {
        if ($x->count < GPU_MIN_RESULTS) continue;
        $s = number_format($min_time/$x->avg_time, 3);
        echo "<li>($s)  $model\n";
    }
    echo "</ol></td>\n";
}
function has_qualifying_models($models) {
    foreach ($models as $x) {
        if ($x->count >= GPU_MIN_RESULTS) return true;
    }
    return false;
}
function show_vendor($vendor, $x) {
    $have_win = has_qualifying_models($x->win);
    $have_linux = has_qualifying_models($x->linux);
    $have_mac = has_qualifying_models($x->mac);
    $n = 0;
    if ($have_win) $n++;
    if ($have_linux) $n++;
    if ($have_mac) $n++;
    if (!$n) return;

    echo "<h2>$vendor</h2>\n";
    $show_total = $n > 1 && has_qualifying_models($x->total);
    start_table();
    echo "<tr>";
    if ($show_total) {
        show_list($x->total, "Total");
    }
    if ($have_win) {
        show_list($x->win, "Windows");
    }
    if ($have_linux) {
        show_list($x->linux, "Linux");
    }
    if ($have_mac) {
        show_list($x->mac, "Mac");
    }
    echo "</tr></table>\n";
}
$d = get_cached_data(86400);
$data = FALSE;
if ($d) {
    $data = unserialize($d);
}

if (!$data || !isset($data->nvidia_amd)) {
    $data = get_gpu_lists();
    set_cached_data(86400, serialize($data));
}
page_head(tra("Top GPU models"));
echo tra("The following lists show the most productive GPU models on different platforms.  Relative speeds, measured by average elapsed time of successful, validated tasks, are shown in parentheses.  Models must have at least ").GPU_MIN_RESULTS.tra(" qualifying results in a list to be shown.");
show_vendor("NVIDIA + ATI/AMD", $data->nvidia_amd);
show_vendor("NVIDIA", $data->cuda);
show_vendor("ATI/AMD", $data->ati);
echo "<p>Generated ".time_str($data->time);
page_tail();

?>
