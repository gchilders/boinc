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

/*
changelog
    squalyl  2010 apr 26    - display details for numbers queued for post processing
                            - removed XML bits
    squalyl  2010 apr 27    - added the "estimated pending relations" column
    debrouxl 2010 apr 27    - changed the "Now crunching" comment and the "~ Pending Rels" title
    squalyl  2010 apr 30    - avoided div/0 when received_relations was zero and computing pending relations
    debrouxl 2010 may 08    - slightly reduced the indication on the minimum numbers of relations
                              (223251500 relations on 10003_250 showed significant oversieving)
    squalyl  2011 aug 26    - add indication of order, to allow counting number per category
             2026 sep 08    - table markup cleanup:
                              * use start_table()/end_table() so the table gets the
                                site theme and the horizontal scroll wrapper
                              * proper heading row via row_heading_array() with
                                scope="col", instead of a bare <tr><th>
                              * moved the "N factored" total out of the table into a
                                paragraph above it; it was a colspan=5 <font size=4>
                                row sitting above the heading row
                              * removed a stray <tr><td>&nbsp</td></tr> spacer row that
                                had one cell in a five column table
                              * dropped the deprecated ALIGN=center row attributes; the
                                stylesheet handles alignment
                              * the static factorization total is now counted at runtime
                                rather than hardcoded as 312, so it cannot drift
                              * static pastebin links upgraded from http to https
                              * escape database values on output, and stop passing null
                                to strpos() (a deprecation warning on PHP 8.1+)
*/

require_once("../inc/boinc_db.inc");
require_once("../inc/boinc_db_rsals.inc");
require_once("../inc/util.inc");
require_once("../inc/translation.inc");

db_init();

if (get_str('xml', true)) {
    die("xml not supported");
}

// Emit the rows for one status group. $label is the text shown in the Status
// column, linked to the result paste when there is one.
//
function display_result($numbers, $label) {
    foreach (array_reverse($numbers) as $number) {
        echo "<tr>";
        echo '<td class="num">'.htmlspecialchars($number->dispname).'</td>';
        echo '<td class="nobr">'
            .htmlspecialchars(trim($number->type.' '.$number->difficulty))
            .'</td>';

        // Prefer a locally mirrored copy of the paste when one exists, so the
        // result stays reachable if pastebin.com loses it.
        $href = null;
        if (!empty($number->pastebin)
            && preg_match('/^[A-Za-z0-9]{4,32}$/', $number->pastebin)
        ) {
            $id = $number->pastebin;
            $local = __DIR__.'/pastebin/'.$id;
            if (is_file($local) && filesize($local) > 0) {
                $href = 'pastebin/'.rawurlencode($id);
            } else {
                $href = 'https://pastebin.com/'.rawurlencode($id);
            }
        }
        if ($href) {
            printf('<td><a href="%s">%s</a></td>',
                htmlspecialchars($href, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($label)
            );
        } else {
            echo '<td>'.htmlspecialchars($label).'</td>';
        }

        // postprocessor holds "completion date|size of factors". It is empty
        // for numbers that have not been through post processing yet, and
        // passing null to strpos() is deprecated as of PHP 8.1.
        $pp = (string)$number->postprocessor;
        if (strpos($pp, '|') !== false) {
            list($date, $factors) = explode('|', $pp, 2);
            echo '<td class="nobr">'.htmlspecialchars(trim($date)).'</td>';
            echo '<td>'.htmlspecialchars(trim($factors)).'</td>';
        } else {
            echo '<td></td><td></td>';
        }
        echo "</tr>\n";
    }
}

// Factorizations completed before the results were tracked in the database.
// Counted at runtime below, so the total on the page cannot drift out of step
// with the list.
$historical = <<<EOT
<tr><td class="num">3,668+</td><td class="nobr">SNFS 319</td><td><a href="https://pastebin.com/fc8fezFR">Factored</a></td><td class="nobr">Apr. 6, 2021</td><td>P85 * P193</td></tr>
<tr><td class="num">6,409+</td><td class="nobr">SNFS 319</td><td><a href="https://pastebin.com/RKNSCp8f">Factored</a></td><td class="nobr">Apr. 5, 2021</td><td>P62 * P249</td></tr>
<tr><td class="num">2,1115+</td><td class="nobr">SNFS 269</td><td><a href="https://pastebin.com/tsAcBDVs">Factored</a></td><td class="nobr">Mar. 9, 2021</td><td>P94 * P160</td></tr>
<tr><td class="num">3,667-</td><td class="nobr">SNFS 318</td><td><a href="https://pastebin.com/KrjunEye">Factored</a></td><td class="nobr">Mar. 20, 2021</td><td>P74 * P202</td></tr>
<tr><td class="num">2,2230M</td><td class="nobr">SNFS 269</td><td><a href="https://pastebin.com/W6LhLbZ0">Factored</a></td><td class="nobr">Feb. 24, 2021</td><td>P77 * P149</td></tr>
<tr><td class="num">2,2210M</td><td class="nobr">SNFS 267</td><td><a href="https://pastebin.com/B1jgvQa6">Factored</a></td><td class="nobr">Feb. 24, 2021</td><td>P93 * P118</td></tr>
<tr><td class="num">2,2330M</td><td class="nobr">GNFS 210</td><td><a href="https://pastebin.com/Dn97SDQE">Factored</a></td><td class="nobr">Feb. 15, 2021</td><td>P75 * P136</td></tr>
<tr><td class="num">2,2158L</td><td class="nobr">SNFS 325</td><td><a href="https://pastebin.com/8Y8ADXaR">Factored</a></td><td class="nobr">May 14, 2021</td><td>P87 * P104 * P106</td></tr>
<tr><td class="num">2,1144+</td><td class="nobr">SNFS 318</td><td><a href="https://pastebin.com/0zfCCxh2">Factored</a></td><td class="nobr">Jan. 31, 2021</td><td>P119 * P155</td></tr>
<tr><td class="num">2,1157+</td><td class="nobr">SNFS 322</td><td><a href="https://pastebin.com/N2s0cXSC">Factored</a></td><td class="nobr">Jan. 21, 2021</td><td>P133 * P137</td></tr>
<tr><td class="num">2,1165+</td><td class="nobr">GNFS 217</td><td><a href="https://pastebin.com/rUeJammv">Factored</a></td><td class="nobr">Jan. 11, 2021</td><td>P77 * P140</td></tr>
<tr><td class="num">2,1084+</td><td class="nobr">SNFS 327</td><td><a href="https://pastebin.com/ZdZLCrU3">Factored</a></td><td class="nobr">Mar. 21, 2021</td><td>P76 * P243</td></tr>
<tr><td class="num">2,2150M</td><td class="nobr">SNFS 259</td><td><a href="https://pastebin.com/as2dNhfh">Factored</a></td><td class="nobr">Oct. 30, 2019</td><td>P94 * P134</td></tr>
<tr><td class="num">2,2126M</td><td class="nobr">SNFS 320</td><td><a href="https://pastebin.com/ivcfLfab">Factored</a></td><td class="nobr">Jan. 8, 2021</td><td>P101 * P118</td></tr>
<tr><td class="num">2,1076+</td><td class="nobr">SNFS 324</td><td><a href="https://pastebin.com/JNc81Thy">Factored</a></td><td class="nobr">Dec. 16, 2020</td><td>P83 * P155</td></tr>
<tr><td class="num">2,1072+</td><td class="nobr">SNFS 323</td><td><a href="https://pastebin.com/wWR35rXL">Factored</a></td><td class="nobr">Apr. 6, 2020</td><td>P68 * P203</td></tr>
<tr><td class="num">2,1063+</td><td class="nobr">SNFS 320</td><td><a href="https://pastebin.com/Mdj1cyB2">Factored</a></td><td class="nobr">Mar. 13, 2020</td><td>P109 * P172</td></tr>
<tr><td class="num">10,371-</td><td class="nobr">SNFS 318</td><td><a href="https://pastebin.com/ghyGcEgW">Factored</a></td><td class="nobr">Nov. 6, 2020</td><td>P124 * P131</td></tr>
<tr><td class="num">10,371+</td><td class="nobr">SNFS 318</td><td><a href="https://pastebin.com/yQ05bym2">Factored</a></td><td class="nobr">Oct. 20, 2020</td><td>P112 * P124</td></tr>
<tr><td class="num">7,376+</td><td class="nobr">SNFS 318</td><td><a href="https://pastebin.com/KsJWjVPU">Factored</a></td><td class="nobr">Nov. 23, 2020</td><td>P92 * P220</td></tr>
<tr><td class="num">6,442+</td><td class="nobr">SNFS 317</td><td><a href="https://pastebin.com/0nrvTZwS">Factored</a></td><td class="nobr">Sept. 16, 2020</td><td>P75 * P95 * P130</td></tr>
<tr><td class="num">7,875M</td><td class="nobr">SNFS 317</td><td><a href="https://pastebin.com/B0gT7m8D">Factored</a></td><td class="nobr">Sept. 1, 2020</td><td>P97 * P157</td></tr>
<tr><td class="num">2,2102L</td><td class="nobr">SNFS 316</td><td><a href="https://pastebin.com/BxFWdWfv">Factored</a></td><td class="nobr">Feb. 20, 2020</td><td>P128 * P154</td></tr>
<tr><td class="num">2,2098L</td><td class="nobr">SNFS 316</td><td><a href="https://pastebin.com/mDg7GcFD">Factored</a></td><td class="nobr">Feb. 17, 2020</td><td>P96 * P203</td></tr>
<tr><td class="num">5,454+</td><td class="nobr">SNFS 319</td><td><a href="https://pastebin.com/krnSA8sL">Factored</a></td><td class="nobr">Oct. 4, 2020</td><td>P81 * P205</td></tr>
<tr><td class="num">12,343-</td><td class="nobr">SNFS 317</td><td><a href="https://pastebin.com/gwBHFHrp">Factored</a></td><td class="nobr">Aug. 12, 2020</td><td>P76 * P166</td></tr>
<tr><td class="num">12,343+</td><td class="nobr">SNFS 317</td><td><a href="https://pastebin.com/N6vyT1UV">Factored</a></td><td class="nobr">July 24, 2020</td><td>P63 * P201</td></tr>
<tr><td class="num">2,1052+</td><td class="nobr">SNFS 317</td><td><a href="https://pastebin.com/f5yRYwjY">Factored</a></td><td class="nobr">July 7, 2019</td><td>P132 * P168</td></tr>
<tr><td class="num">12,293-</td><td class="nobr">SNFS 317</td><td><a href="https://pastebin.com/nyfSpuWq">Factored</a></td><td class="nobr">July 8, 2020</td><td>P105 * P134</td></tr>
<tr><td class="num">12,293+</td><td class="nobr">SNFS 317</td><td><a href="https://pastebin.com/L8ECpRLA">Factored</a></td><td class="nobr">June 24, 2020</td><td>P147 * P156</td></tr>
<tr><td class="num">5,452+</td><td class="nobr">SNFS 316</td><td><a href="https://pastebin.com/68wvFNvD">Factored</a></td><td class="nobr">May 28, 2020</td><td>P101 * P146</td></tr>
<tr><td class="num">3,662+</td><td class="nobr">SNFS 316</td><td><a href="https://pastebin.com/6vhC3jad">Factored</a></td><td class="nobr">May 12, 2020</td><td>P98 * P145</td></tr>
<tr><td class="num">3,661-</td><td class="nobr">SNFS 316</td><td><a href="https://pastebin.com/iXfgNVg7">Factored</a></td><td class="nobr">Apr. 30, 2020</td><td>P134 * P182</td></tr>
<tr><td class="num">7,373+</td><td class="nobr">SNFS 316</td><td><a href="https://pastebin.com/sHgCAafC">Factored</a></td><td class="nobr">Apr. 14, 2020</td><td>P122 * P182</td></tr>
<tr><td class="num">11,302+</td><td class="nobr">SNFS 315</td><td><a href="https://pastebin.com/e1RCjVnP">Factored</a></td><td class="nobr">Mar. 30, 2020</td><td>P108 * P118</td></tr>
<tr><td class="num">3,659+</td><td class="nobr">SNFS 315</td><td><a href="https://pastebin.com/XWErVB3V">Factored</a></td><td class="nobr">Mar. 19, 2020</td><td>P73 * P91 * P143</td></tr>
<tr><td class="num">6,404+</td><td class="nobr">SNFS 316</td><td><a href="https://pastebin.com/deekS7FT">Factored</a></td><td class="nobr">Dec. 28, 2019</td><td>P74 * P193</td></tr>
<tr><td class="num">5,449-</td><td class="nobr">SNFS 315</td><td><a href="https://pastebin.com/YWkDHTnr">Factored</a></td><td class="nobr">Mar. 2, 2020</td><td>P107 * P165</td></tr>
<tr><td class="num">5,449+</td><td class="nobr">SNFS 315</td><td><a href="https://pastebin.com/P3ETxXY5">Factored</a></td><td class="nobr">Nov. 14, 2019</td><td>P108 * P165</td></tr>
<tr><td class="num">10,313+</td><td class="nobr">SNFS 313</td><td><a href="https://pastebin.com/Ue8C72Qw">Factored</a></td><td class="nobr">Oct. 11, 2019</td><td>P108 * P122</td></tr>
<tr><td class="num">6,469+</td><td class="nobr">SNFS 313</td><td><a href="https://pastebin.com/gVk8Nduw">Factored</a></td><td class="nobr">Aug. 18, 2019</td><td>P89 * P155</td></tr>
<tr><td class="num">2,2078M</td><td class="nobr">SNFS 313</td><td><a href="https://pastebin.com/fLaJa53u">Factored</a></td><td class="nobr">May 23, 2019</td><td>P135 * P178</td></tr>
<tr><td class="num">2,1037+</td><td class="nobr">SNFS 312</td><td><a href="https://pastebin.com/3s5SfP5e">Factored</a></td><td class="nobr">May 10, 2019</td><td>P69 * P141</td></tr>
<tr><td class="num">6,401-</td><td class="nobr">SNFS 313</td><td><a href="https://pastebin.com/9HD35TUG">Factored</a></td><td class="nobr">Aug. 6, 2019</td><td>P114 * P160</td></tr>
<tr><td class="num">3,763-</td><td class="nobr">SNFS 312</td><td><a href="https://pastebin.com/vNKKm1hT">Factored</a></td><td class="nobr">July 30, 2019</td><td>P104 * P168</td></tr>
<tr><td class="num">3,763+</td><td class="nobr">SNFS 312</td><td><a href="https://pastebin.com/NZnBHbVk">Factored</a></td><td class="nobr">July 27, 2019</td><td>P91 * P215</td></tr>
<tr><td class="num">10,364+</td><td class="nobr">SNFS 312</td><td><a href="https://pastebin.com/G5HQEeGx">Factored</a></td><td class="nobr">July 29, 2019</td><td>P60 * P82 * P127</td></tr>
<tr><td class="num">10,338+</td><td class="nobr">SNFS 312</td><td><a href="https://pastebin.com/0TwrQVAc">Factored</a></td><td class="nobr">July 24, 2019</td><td>P67 * P90 * P151</td></tr>
<tr><td class="num">12,289-</td><td class="nobr">SNFS 312</td><td><a href="https://pastebin.com/8ab7PvzV">Factored</a></td><td class="nobr">June 14, 2019</td><td>P76 * P173</td></tr>
<tr><td class="num">12,289+</td><td class="nobr">SNFS 312</td><td><a href="https://pastebin.com/2EvWBuzW">Factored</a></td><td class="nobr">May 29, 2019</td><td>P112 * P153</td></tr>
<tr><td class="num">5,446+</td><td class="nobr">SNFS 312</td><td><a href="https://pastebin.com/2vMaiueX">Factored</a></td><td class="nobr">Apr. 16, 2019</td><td>P122 * P152</td></tr>
<tr><td class="num">3,653+</td><td class="nobr">SNFS 312</td><td><a href="https://pastebin.com/7e2hSXqr">Factored</a></td><td class="nobr">Apr. 10, 2019</td><td>P98 * P173</td></tr>
<tr><td class="num">10,311+</td><td class="nobr">SNFS 312</td><td><a href="https://pastebin.com/X0bnvFAT">Factored</a></td><td class="nobr">Mar. 11, 2019</td><td>P66 * P67 * P90</td></tr>
<tr><td class="num">2,2066M</td><td class="nobr">SNFS 311</td><td><a href="https://pastebin.com/8dVvJCD1">Factored</a></td><td class="nobr">Feb. 23, 2019</td><td>P121 * P131</td></tr>
<tr><td class="num">2,2066L</td><td class="nobr">SNFS 311</td><td><a href="https://pastebin.com/gk0JwNwz">Factored</a></td><td class="nobr">Feb. 22, 2019</td><td>P88 * P178</td></tr>
<tr><td class="num">2,1033+</td><td class="nobr">SNFS 311</td><td><a href="https://pastebin.com/ddBFNeds">Factored</a></td><td class="nobr">Feb. 20, 2019</td><td>P100 * P155</td></tr>
<tr><td class="num">2,1204+</td><td class="nobr">SNFS 311</td><td><a href="https://pastebin.com/62azKSM4">Factored</a></td><td class="nobr">Feb. 20, 2019</td><td>P111 * P194</td></tr>
<tr><td class="num">2,2062M</td><td class="nobr">SNFS 311</td><td><a href="https://pastebin.com/UTAHtLsT">Factored</a></td><td class="nobr">Feb. 1, 2019</td><td>P60 * P199</td></tr>
<tr><td class="num">2,2062L</td><td class="nobr">SNFS 311</td><td><a href="https://pastebin.com/RM9ByY2y">Factored</a></td><td class="nobr">Feb. 10, 2019</td><td>P92 * P214</td></tr>
<tr><td class="num">7,367-</td><td class="nobr">SNFS 310</td><td><a href="https://pastebin.com/wRp7RbMV">Factored</a></td><td class="nobr">Dec. 28, 2018</td><td>P82 * P171</td></tr>
<tr><td class="num">7,367+</td><td class="nobr">SNFS 310</td><td><a href="https://pastebin.com/7naQiSxW">Factored</a></td><td class="nobr">Jan. 5, 2019</td><td>P75 * P226</td></tr>
<tr><td class="num">2,1133+</td><td class="nobr">SNFS 310</td><td><a href="https://pastebin.com/nmZucBQk">Factored</a></td><td class="nobr">Jan. 30, 2019</td><td>P68 * P192</td></tr>
<tr><td class="num">6,397+</td><td class="nobr">SNFS 309</td><td><a href="https://pastebin.com/hLXbk90R">Factored</a></td><td class="nobr">Sept. 22, 2018</td><td>P97 * P135</td></tr>
<tr><td class="num">5,481-</td><td class="nobr">SNFS 311</td><td><a href="https://pastebin.com/20nG7m3X">Factored</a></td><td class="nobr">Aug. 27, 2018</td><td>P138 * P165</td></tr>
<tr><td class="num">5,481+</td><td class="nobr">SNFS 311</td><td><a href="https://pastebin.com/3F7SWWqT">Factored</a></td><td class="nobr">Aug. 28, 2018</td><td>P120 * P121</td></tr>
<tr><td class="num">5,443+</td><td class="nobr">SNFS 310</td><td><a href="https://pastebin.com/ZDpwFW96">Factored</a></td><td class="nobr">July 2, 2018</td><td>P95 * P174</td></tr>
<tr><td class="num">5,484+</td><td class="nobr">SNFS 308</td><td><a href="https://pastebin.com/3NRZdKEc">Factored</a></td><td class="nobr">Sept. 23, 2018</td><td>P126 * P142</td></tr>
<tr><td class="num">3,647+</td><td class="nobr">SNFS 309</td><td><a href="https://pastebin.com/yv2rYhtG">Factored</a></td><td class="nobr">June 21, 2018</td><td>P134 * P144</td></tr>
<tr><td class="num">12,283-</td><td class="nobr">SNFS 306</td><td><a href="https://pastebin.com/p9YYbJED">Factored</a></td><td class="nobr">May 24, 2018</td><td>P116 * P129</td></tr>
<tr><td class="num">12,329+</td><td class="nobr">SNFS 305</td><td><a href="https://pastebin.com/E1YrYRCv">Factored</a></td><td class="nobr">May 7, 2018</td><td>P114 * P154</td></tr>
<tr><td class="num">12,281-</td><td class="nobr">SNFS 305</td><td><a href="https://pastebin.com/E5RitJru">Factored</a></td><td class="nobr">May 15, 2018</td><td>P68 * P74 * P94</td></tr>
<tr><td class="num">12,299+</td><td class="nobr">SNFS 298</td><td><a href="https://pastebin.com/EYfJ1dah">Factored</a></td><td class="nobr">Apr. 25, 2018</td><td>P98 * P104</td></tr>
<tr><td class="num">11,298+</td><td class="nobr">SNFS 313</td><td><a href="https://pastebin.com/cXqsPFDL">Factored</a></td><td class="nobr">Apr. 12, 2018</td><td>P121 * P122</td></tr>
<tr><td class="num">11,649L</td><td class="nobr">SNFS 308</td><td><a href="https://pastebin.com/mQbk5jf1">Factored</a></td><td class="nobr">Apr. 14, 2018</td><td>P62 * P74 * P86</td></tr>
<tr><td class="num">11,319-</td><td class="nobr">SNFS 302</td><td><a href="https://pastebin.com/RuN4AuZB">Factored</a></td><td class="nobr">Mar. 7, 2018</td><td>P71 * P172</td></tr>
<tr><td class="num">11,293-</td><td class="nobr">SNFS 306</td><td><a href="https://pastebin.com/q7323b3U">Factored</a></td><td class="nobr">Mar. 22, 2018</td><td>P73 * P114 * P114</td></tr>
<tr><td class="num">11,293+</td><td class="nobr">SNFS 306</td><td><a href="https://pastebin.com/MycrTgWq">Factored</a></td><td class="nobr">Feb. 23, 2018</td><td>P96 * P117</td></tr>
<tr><td class="num">11,292+</td><td class="nobr">SNFS 306</td><td><a href="https://pastebin.com/NJ5NL86F">Factored</a></td><td class="nobr">Feb. 18, 2018</td><td>P119 * P140</td></tr>
<tr><td class="num">7,847L</td><td class="nobr">SNFS 307</td><td><a href="https://pastebin.com/0Pwjm0eS">Factored</a></td><td class="nobr">Feb. 19, 2018</td><td>P80 * P146</td></tr>
<tr><td class="num">7,833M</td><td class="nobr">SNFS 302</td><td><a href="https://pastebin.com/xE0wSb2A">Factored</a></td><td class="nobr">Feb. 13, 2018</td><td>P105 * P108</td></tr>
<tr><td class="num">7,833L</td><td class="nobr">SNFS 302</td><td><a href="https://pastebin.com/tNWr2U6k">Factored</a></td><td class="nobr">Feb. 11, 2018</td><td>P85 * P157</td></tr>
<tr><td class="num">7,413-</td><td class="nobr">SNFS 300</td><td><a href="https://pastebin.com/26CbZwrz">Factored</a></td><td class="nobr">Feb. 7, 2018</td><td>P83 * P138</td></tr>
<tr><td class="num">7,361-</td><td class="nobr">SNFS 305</td><td><a href="https://pastebin.com/CKbK3zpi">Factored</a></td><td class="nobr">Feb. 5, 2018</td><td>P84 * P206</td></tr>
<tr><td class="num">7,359-</td><td class="nobr">SNFS 304</td><td><a href="https://pastebin.com/4vyijA4G">Factored</a></td><td class="nobr">Feb. 3, 2018</td><td>P79 * P146</td></tr>
<tr><td class="num">7,359+</td><td class="nobr">SNFS 304</td><td><a href="https://pastebin.com/T9W1RfLM">Factored</a></td><td class="nobr">Jan. 13, 2018</td><td>P70 * P101 * P108</td></tr>
<tr><td class="num">7,356+</td><td class="nobr">SNFS 301</td><td><a href="https://pastebin.com/dmwdYKv8">Factored</a></td><td class="nobr">Jan. 3, 2018</td><td>P72 * P135</td></tr>
<tr><td class="num">6,394+</td><td class="nobr">SNFS 308</td><td><a href="https://pastebin.com/frakE4mJ">Factored</a></td><td class="nobr">Dec. 28, 2017</td><td>P63 * P211</td></tr>
<tr><td class="num">6,391-</td><td class="nobr">SNFS 304</td><td><a href="https://pastebin.com/gb2LD1Ej">Factored</a></td><td class="nobr">Oct. 21, 2017</td><td>P123 * P152</td></tr>
<tr><td class="num">6,391+</td><td class="nobr">SNFS 304</td><td><a href="https://pastebin.com/caFfVPbd">Factored</a></td><td class="nobr">Oct. 20, 2017</td><td>P93 * P167</td></tr>
<tr><td class="num">5,511-</td><td class="nobr">SNFS 306</td><td><a href="https://pastebin.com/Hz58xVnL">Factored</a></td><td class="nobr">Sept. 20, 2017</td><td>P93 * P180</td></tr>
<tr><td class="num">5,511+</td><td class="nobr">SNFS 306</td><td><a href="https://pastebin.com/Ftzh5sh0">Factored</a></td><td class="nobr">Sept. 17, 2017</td><td>P79 * P172</td></tr>
<tr><td class="num">5,497-</td><td class="nobr">SNFS 298</td><td><a href="https://pastebin.com/WFXjC2ER">Factored</a></td><td class="nobr">Sept. 17, 2017</td><td>P94 * P180</td></tr>
<tr><td class="num">5,497+</td><td class="nobr">SNFS 298</td><td><a href="https://pastebin.com/PDgDc63L">Factored</a></td><td class="nobr">Sept. 15, 2017</td><td>P84 * P200</td></tr>
<tr><td class="num">5,473-</td><td class="nobr">SNFS 301</td><td><a href="https://pastebin.com/sEvZx0L7">Factored</a></td><td class="nobr">Sept. 18, 2017</td><td>P111 * P157</td></tr>
<tr><td class="num">5,439+</td><td class="nobr">SNFS 307</td><td><a href="https://pastebin.com/Q5pJGfNE">Factored</a></td><td class="nobr">Sept. 2, 2017</td><td>P83 * P167</td></tr>
<tr><td class="num">5,436+</td><td class="nobr">SNFS 306</td><td><a href="https://pastebin.com/1SLT5cVd">Factored</a></td><td class="nobr">Aug. 6, 2017</td><td>P98 * P181</td></tr>
<tr><td class="num">5,431-</td><td class="nobr">SNFS 302</td><td><a href="https://pastebin.com/jiNFQpTa">Factored</a></td><td class="nobr">Aug. 5, 2017</td><td>P74 * P75 * P118</td></tr>
<tr><td class="num">5,431+</td><td class="nobr">SNFS 302</td><td><a href="https://pastebin.com/Vwm7PCCK">Factored</a></td><td class="nobr">Aug. 4, 2017</td><td>P73 * P101 * P120</td></tr>
<tr><td class="num">HP2_4496_310</td><td class="nobr">GNFS 207</td><td><a href="https://pastebin.com/RFkgXQj7">Factored</a></td><td class="nobr">Aug. 4, 2017</td><td>P77 * P131</td></tr>
<tr><td class="num">3,749+</td><td class="nobr">SNFS 306</td><td><a href="https://pastebin.com/5CF1Gh3B">Factored</a></td><td class="nobr">July 31, 2017</td><td>P91 * P136</td></tr>
<tr><td class="num">3,721+</td><td class="nobr">SNFS 295</td><td><a href="https://pastebin.com/1Lskq1ba">Factored</a></td><td class="nobr">July 2, 2017</td><td>P136 * P138</td></tr>
<tr><td class="num">3,704+</td><td class="nobr">SNFS 305</td><td><a href="https://pastebin.com/KpnyRu2y">Factored</a></td><td class="nobr">July 6, 2017</td><td>P66 * P89 * P108</td></tr>
<tr><td class="num">3,689-</td><td class="nobr">SNFS 303</td><td><a href="https://pastebin.com/Te1rsGVT">Factored</a></td><td class="nobr">June 29, 2017</td><td>P71 * P97 * P125</td></tr>
<tr><td class="num">3,689+</td><td class="nobr">SNFS 303</td><td><a href="https://pastebin.com/UfuqZtLd">Factored</a></td><td class="nobr">June 25, 2017</td><td>P111 * P163</td></tr>
<tr><td class="num">3,682+</td><td class="nobr">SNFS 296</td><td><a href="https://pastebin.com/drM3LS0j">Factored</a></td><td class="nobr">June 29, 2017</td><td>P69 * P83 * P110</td></tr>
<tr><td class="num">3,676+</td><td class="nobr">SNFS 298</td><td><a href="https://pastebin.com/di7hmZp9">Factored</a></td><td class="nobr">June 22, 2017</td><td>P83 * P148</td></tr>
<tr><td class="num">3,643+</td><td class="nobr">SNFS 307</td><td><a href="https://pastebin.com/VBm3vkVY">Factored</a></td><td class="nobr">June 2, 2017</td><td>P69 * P142</td></tr>
<tr><td class="num">3,632+</td><td class="nobr">SNFS 302</td><td><a href="https://pastebin.com/zMyKtDEx">Factored</a></td><td class="nobr">May 9, 2017</td><td>P75 * P146</td></tr>
<tr><td class="num">3,631+</td><td class="nobr">SNFS 301</td><td><a href="https://pastebin.com/nqQh9Jar">Factored</a></td><td class="nobr">Apr. 26, 2017</td><td>P72 * P147</td></tr>
<tr><td class="num">B248</td><td class="nobr">GNFS 208</td><td><a href="https://pastebin.com/uwur0Vk7">Factored</a></td><td class="nobr">Oct. 3, 2017</td><td>P101 * P107</td></tr>
<tr><td class="num">E148</td><td class="nobr">GNFS 202</td><td><a href="https://pastebin.com/t1WM1knz">Factored</a></td><td class="nobr">Feb. 28, 2017</td><td>P97 * P105</td></tr>
<tr><td class="num">E192</td><td class="nobr">GNFS 200</td><td><a href="https://pastebin.com/RYytpx6Z">Factored</a></td><td class="nobr">Feb. 20, 2017</td><td>P66 * P134</td></tr>
<tr><td class="num">B228</td><td class="nobr">GNFS 198</td><td><a href="https://pastebin.com/RxAFdL5b">Factored</a></td><td class="nobr">Feb. 13, 2017</td><td>P73 * P125</td></tr>
<tr><td class="num">E162</td><td class="nobr">GNFS 193</td><td><a href="https://pastebin.com/dugKGaGM">Factored</a></td><td class="nobr">Feb. 13, 2017</td><td>P72 * P122</td></tr>
<tr><td class="num">10,304+</td><td class="nobr">SNFS 304</td><td><a href="https://pastebin.com/iRt0tVLG">Factored</a></td><td class="nobr">Apr. 14, 2017</td><td>P95 * P187</td></tr>
<tr><td class="num">10,302+</td><td class="nobr">SNFS 302</td><td><a href="https://pastebin.com/LQkg9tjW">Factored</a></td><td class="nobr">Mar. 13, 2017</td><td>P144 * P146</td></tr>
<tr><td class="num">12,274+</td><td class="nobr">SNFS 296</td><td><a href="https://pastebin.com/YpXpqFB2">Factored</a></td><td class="nobr">Feb. 21, 2017</td><td>P83 * P131</td></tr>
<tr><td class="num">12,277-</td><td class="nobr">SNFS 299</td><td><a href="https://pastebin.com/hrTCL03b">Factored</a></td><td class="nobr">Feb. 12, 2017</td><td>P92 * P194</td></tr>
<tr><td class="num">6,389+</td><td class="nobr">SNFS 303</td><td><a href="https://pastebin.com/6N2HjzTB">Factored</a></td><td class="nobr">Feb. 9, 2017</td><td>P101 * P142</td></tr>
<tr><td class="num">5,424+</td><td class="nobr">SNFS 297</td><td><a href="https://pastebin.com/59jiR2vF">Factored</a></td><td class="nobr">Feb. 2, 2017</td><td>P123 * P159</td></tr>
<tr><td class="num">2,2338L</td><td class="nobr">SNFS 302</td><td><a href="https://pastebin.com/J9LnvXLd">Factored</a></td><td class="nobr">Dec. 30, 2016</td><td>P80 * P173</td></tr>
<tr><td class="num">2,1183+</td><td class="nobr">SNFS 306</td><td><a href="https://pastebin.com/KDyEzSMf">Factored</a></td><td class="nobr">Dec. 28, 2016</td><td>P101 * P160</td></tr>
<tr><td class="num">3,619+</td><td class="nobr">SNFS 296</td><td><a href="https://pastebin.com/mUQcbNbX">Factored</a></td><td class="nobr">Dec. 23, 2016</td><td>P107 * P166</td></tr>
<tr><td class="num">3,619-</td><td class="nobr">SNFS 296</td><td><a href="https://pastebin.com/f6K174r5">Factored</a></td><td class="nobr">Feb. 19, 2017</td><td>P97 * P111</td></tr>
<tr><td class="num">10,325+</td><td class="nobr">GNFS 197</td><td><a href="https://pastebin.com/yHsqMs59">Factored</a></td><td class="nobr">Dec. 5, 2016</td><td>P84 * P114</td></tr>
<tr><td class="num">3,703+</td><td class="nobr">GNFS 208</td><td><a href="https://pastebin.com/wdeK80f8">Factored</a></td><td class="nobr">Dec. 16, 2016</td><td>P88 * P121</td></tr>
<tr><td class="num">7,373-</td><td class="nobr">GNFS 204</td><td><a href="https://pastebin.com/jJvG0Spx">Factored</a></td><td class="nobr">Nov. 12, 2016</td><td>P101 * P104</td></tr>
<tr><td class="num">11,671M</td><td class="nobr">GNFS 202</td><td><a href="https://pastebin.com/2stLGQqc">Factored</a></td><td class="nobr">Nov. 16, 2016</td><td>P79 * P124</td></tr>
<tr><td class="num">6,460+</td><td class="nobr">GNFS 202</td><td><a href="https://pastebin.com/jHfbAmf3">Factored</a></td><td class="nobr">Nov. 5, 2016</td><td>P101 * P101</td></tr>
<tr><td class="num">2,2530M</td><td class="nobr">GNFS 196</td><td><a href="https://pastebin.com/Ww3YSibG">Factored</a></td><td class="nobr">Oct. 30, 2016</td><td>P75 * P121</td></tr>
<tr><td class="num">5,1085L</td><td class="nobr">GNFS 196</td><td><a href="https://pastebin.com/cZNyrKZE">Factored</a></td><td class="nobr">Oct. 28, 2016</td><td>P71 * P125</td></tr>
<tr><td class="num">2,2186M</td><td class="nobr">GNFS 194</td><td><a href="https://pastebin.com/kpXwbZyt">Factored</a></td><td class="nobr">Oct. 23, 2016</td><td>P62 * P133</td></tr>
<tr><td class="num">2,1079+</td><td class="nobr">SNFS 300</td><td><a href="https://pastebin.com/irC5vRRZ">Factored</a></td><td class="nobr">Oct. 22, 2016</td><td>P87 * P174</td></tr>
<tr><td class="num">5,485+</td><td class="nobr">GNFS 192</td><td><a href="https://pastebin.com/MvCekKbG">Factored</a></td><td class="nobr">Oct. 10, 2016</td><td>P92 * P100</td></tr>
<tr><td class="num">3,790+</td><td class="nobr">GNFS 191</td><td><a href="https://pastebin.com/8hiZ6dUK">Factored</a></td><td class="nobr">Oct. 3, 2016</td><td>P77 * P114</td></tr>
<tr><td class="num">7,401-</td><td class="nobr">GNFS 190</td><td><a href="https://pastebin.com/2VEQB3bj">Factored</a></td><td class="nobr">Oct. 8, 2016</td><td>P65 * P125</td></tr>
<tr><td class="num">2,2042M</td><td class="nobr">SNFS 308</td><td><a href="https://pastebin.com/kvkS0tyi">Factored</a></td><td class="nobr">Oct. 20, 2016</td><td>P101 * P199</td></tr>
<tr><td class="num">2,2042L</td><td class="nobr">SNFS 308</td><td><a href="https://pastebin.com/U2EaaGsE">Factored</a></td><td class="nobr">Oct. 10, 2016</td><td>P107 * P200</td></tr>
<tr><td class="num">2,2026M</td><td class="nobr">SNFS 306</td><td><a href="https://pastebin.com/aA42f6ey">Factored</a></td><td class="nobr">Oct. 3, 2016</td><td>P73 * P194</td></tr>
<tr><td class="num">2,2018L</td><td class="nobr">SNFS 304</td><td><a href="https://pastebin.com/jHbQKejy">Factored</a></td><td class="nobr">Sept. 27, 2016</td><td>P112 * P128</td></tr>
<tr><td class="num">2,2006M</td><td class="nobr">SNFS 302</td><td><a href="https://pastebin.com/ftQu4ZrZ">Factored</a></td><td class="nobr">Sept. 17, 2016</td><td>P118 * P158</td></tr>
<tr><td class="num">2,1982M</td><td class="nobr">SNFS 299</td><td><a href="https://pastebin.com/RJG5xquY">Factored</a></td><td class="nobr">Sept. 7, 2016</td><td>P101 * P149</td></tr>
<tr><td class="num">2,1954M</td><td class="nobr">SNFS 295</td><td><a href="https://pastebin.com/EuQMsGz7">Factored</a></td><td class="nobr">Aug. 29, 2016</td><td>P80 * P167</td></tr>
<tr><td class="num">2,1934M</td><td class="nobr">SNFS 291</td><td><a href="https://pastebin.com/6yKizitn">Factored</a></td><td class="nobr">Aug. 21, 2016</td><td>P102 * P123</td></tr>
<tr><td class="num">2,1019+</td><td class="nobr">SNFS 307</td><td><a href="https://pastebin.com/Liu8CD5g">Factored</a></td><td class="nobr">Sept. 15, 2016</td><td>P77 * P147</td></tr>
<tr><td class="num">2,1009+</td><td class="nobr">SNFS 304</td><td><a href="https://pastebin.com/FWrQU0rb">Factored</a></td><td class="nobr">Sept. 7, 2016</td><td>P108 * P136</td></tr>
<tr><td class="num">7,353+</td><td class="nobr">SNFS 300</td><td><a href="https://pastebin.com/w6ZYNtNm">Factored</a></td><td class="nobr">Aug. 4, 2016</td><td>P89 * P152</td></tr>
<tr><td class="num">7,353-</td><td class="nobr">SNFS 300</td><td><a href="https://pastebin.com/ZbN6fjx2">Factored</a></td><td class="nobr">Aug. 4, 2016</td><td>P98 * P165</td></tr>
<tr><td class="num">6,383-</td><td class="nobr">SNFS 299</td><td><a href="https://pastebin.com/N0hgKLks">Factored</a></td><td class="nobr">Aug. 3, 2016</td><td>P92 * P172</td></tr>
<tr><td class="num">5,422+</td><td class="nobr">SNFS 295</td><td><a href="https://pastebin.com/nnVaTYRh">Factored</a></td><td class="nobr">Aug. 3, 2016</td><td>P91 * P145</td></tr>
<tr><td class="num">XYYXF 147,136</td><td class="nobr">GNFS 197</td><td><a href="https://pastebin.com/GkJkM310">Factored</a></td><td class="nobr">Aug. 31, 2016</td><td>P93 * P104</td></tr>
<tr><td class="num">11,283-</td><td class="nobr">SNFS 295</td><td><a href="https://pastebin.com/7drU9bsf">Factored</a></td><td class="nobr">July 31, 2016</td><td>P118 * P150</td></tr>
<tr><td class="num">11,283+</td><td class="nobr">SNFS 295</td><td><a href="https://pastebin.com/NBhpXDDh">Factored</a></td><td class="nobr">Aug. 1, 2016</td><td>P92 * P167</td></tr>
<tr><td class="num">3,617-</td><td class="nobr">SNFS 295</td><td><a href="https://pastebin.com/ZmAyDCjG">Factored</a></td><td class="nobr">Aug. 1, 2016</td><td>P110 * P168</td></tr>
<tr><td class="num">XYYXF 139,122</td><td class="nobr">SNFS 290</td><td><a href="https://pastebin.com/hzY3x80K">Factored</a></td><td class="nobr">Aug. 28, 2016</td><td>P118 * P168</td></tr>
<tr><td class="num">12,271+</td><td class="nobr">SNFS 293</td><td><a href="https://pastebin.com/pbDP9xCp">Factored</a></td><td class="nobr">Aug. 21, 2016</td><td>P109 * P142</td></tr>
<tr><td class="num">12,271-</td><td class="nobr">SNFS 293</td><td><a href="https://pastebin.com/yKiWxPxK">Factored</a></td><td class="nobr">June 19, 2016</td><td>P97 * P138</td></tr>
<tr><td class="num">2,1028+</td><td class="nobr">SNFS 310</td><td><a href="https://pastebin.com/PfXgLEfK">Factored</a></td><td class="nobr">May 14, 2016</td><td>P74 * P168</td></tr>
<tr><td class="num">2,991+</td><td class="nobr">SNFS 298</td><td><a href="https://pastebin.com/q9DWn0b5">Factored</a></td><td class="nobr">Apr. 24, 2016</td><td>P75 * P150</td></tr>
<tr><td class="num">2,989+</td><td class="nobr">SNFS 298</td><td><a href="https://pastebin.com/4bqYgbGZ">Factored</a></td><td class="nobr">Apr. 24, 2016</td><td>P76 * P79 * P96</td></tr>
<tr><td class="num">7,346+</td><td class="nobr">SNFS 294</td><td><a href="https://pastebin.com/BcjxRFCE">Factored</a></td><td class="nobr">Apr. 6, 2016</td><td>P85 * P167</td></tr>
<tr><td class="num">5,419+</td><td class="nobr">SNFS 293</td><td><a href="https://pastebin.com/DGi6DKvJ">Factored</a></td><td class="nobr">Mar. 25, 2016</td><td>P100 * P136</td></tr>
<tr><td class="num">5,419-</td><td class="nobr">SNFS 293</td><td><a href="https://pastebin.com/eyBrT0EA">Factored</a></td><td class="nobr">Mar. 19, 2016</td><td>P78 * P147</td></tr>
<tr><td class="num">10,298+</td><td class="nobr">SNFS 299</td><td><a href="https://pastebin.com/UgMR56Un">Factored</a></td><td class="nobr">Feb. 26, 2016</td><td>P128 * P139</td></tr>
<tr><td class="num">6,376+</td><td class="nobr">SNFS 293</td><td><a href="https://pastebin.com/DUiuCNaF">Factored</a></td><td class="nobr">Feb. 7, 2016</td><td>P98 * P127</td></tr>
<tr><td class="num">2,1285-</td><td class="nobr">GNFS 218</td><td><a href="https://pastebin.com/JGXg5x0T">Factored</a></td><td class="nobr">Nov. 4, 2016</td><td>P104 * P114</td></tr>
<tr><td class="num">2,983+</td><td class="nobr">SNFS 296</td><td><a href="https://pastebin.com/8eD6BaRz">Factored</a></td><td class="nobr">Dec. 31, 2015</td><td>P85 * P151</td></tr>
<tr><td class="num">6,490+</td><td class="nobr">GNFS 212</td><td><a href="https://pastebin.com/EnR8dw6p">Factored</a></td><td class="nobr">Nov. 14, 2015</td><td>P70 * P142</td></tr>
<tr><td class="num">10,359-</td><td class="nobr">GNFS 208</td><td><a href="https://pastebin.com/H5n6KD8t">Factored</a></td><td class="nobr">July 3, 2015</td><td>P101 * P108</td></tr>
<tr><td class="num">2,2218M</td><td class="nobr">GNFS 206</td><td><a href="https://pastebin.com/YRPuCm2e">Factored</a></td><td class="nobr">May 10, 2015</td><td>P83 * P124</td></tr>
<tr><td class="num">3,805-</td><td class="nobr">GNFS 199</td><td>Factored</td><td class="nobr">Apr. 15, 2015</td><td>P68 * P131</td></tr>
<tr><td class="num">3,734+</td><td class="nobr">GNFS 196</td><td>Factored</td><td class="nobr">Mar. 22, 2015</td><td>P62 * P134</td></tr>
<tr><td class="num">F1941</td><td class="nobr">SNFS 270</td><td>Factored</td><td class="nobr">Mar. 2, 2015</td><td>P66 * P167</td></tr>
<tr><td class="num">6,401+</td><td class="nobr">GNFS 194</td><td>Factored</td><td class="nobr">Feb. 24, 2015</td><td>P96 * P99</td></tr>
<tr><td class="num">2,2590L</td><td class="nobr">GNFS 192</td><td>Factored</td><td class="nobr">Feb. 24, 2015</td><td>P93 * P100</td></tr>
<tr><td class="num">3,697+</td><td class="nobr">GNFS 220.9</td><td>Factored</td><td class="nobr">Feb. 20, 2015</td><td>P67 * P76 * P78</td></tr>
<tr><td class="num">3,766+</td><td class="nobr">GNFS 215.5</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=530">Factored</a></td><td class="nobr">Mar. 26, 2014</td><td>P66 * P75 * P76</td></tr>
<tr><td class="num">7,394+</td><td class="nobr">GNFS 196.1</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=443">Factored</a></td><td class="nobr">Sept. 16, 2013</td><td>P71 * P126</td></tr>
<tr><td class="num">10,770M</td><td class="nobr">GNFS 211.9</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=445">Factored</a></td><td class="nobr">Sept. 18, 2013</td><td>P102 * P111</td></tr>
<tr><td class="num">3,706+</td><td class="nobr">GNFS 206.6</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=438">Factored</a></td><td class="nobr">Aug. 1, 2013</td><td>P62 * P145</td></tr>
<tr><td class="num">3,745+</td><td class="nobr">GNFS 201.0</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=437">Factored</a></td><td class="nobr">June 15, 2013</td><td>P62 * P140</td></tr>
<tr><td class="num">L1809</td><td class="nobr">SNFS 252.0</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=430">Factored</a></td><td class="nobr">May 21, 2013</td><td>P88 * P124</td></tr>
<tr><td class="num">L1803</td><td class="nobr">SNFS 251.2</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=429">Factored</a></td><td class="nobr">May 12, 2013</td><td>P96 * P120</td></tr>
<tr><td class="num">L1201</td><td class="nobr">SNFS 251.0</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=427">Factored</a></td><td class="nobr">May 6, 2013</td><td>P81 * P138</td></tr>
<tr><td class="num">L1797</td><td class="nobr">SNFS 250.4</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=423">Factored</a></td><td class="nobr">Apr. 21, 2012</td><td>P72 * P115</td></tr>
<tr><td class="num">F1229</td><td class="nobr">SNFS 256.5</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=424">Factored</a></td><td class="nobr">Apr. 28, 2012</td><td>P79 * P82 * P82</td></tr>
<tr><td class="num">F1839</td><td class="nobr">SNFS 256.2</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=418">Factored</a></td><td class="nobr">Mar. 24, 2013</td><td>P66 * P90 * P98</td></tr>
<tr><td class="num">F1821</td><td class="nobr">SNFS 253.7</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=417">Factored</a></td><td class="nobr">Mar. 13, 2013</td><td>P66 * P73 * P80</td></tr>
<tr><td class="num">F1797</td><td class="nobr">SNFS 250.4</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=415">Factored</a></td><td class="nobr">Mar. 5, 2013</td><td>P112 * P136</td></tr>
<tr><td class="num">2,1049+</td><td class="nobr">SNFS 316.1</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=431">Factored</a></td><td class="nobr">May 28, 2013</td><td>P104 * P115</td></tr>
<tr><td class="num">5233,71-</td><td class="nobr">SNFS 267.8</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=413">Factored</a></td><td class="nobr">Mar. 3, 2013</td><td>P93 * P169</td></tr>
<tr><td class="num">3617523089023,19-</td><td class="nobr">SNFS 238.6</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=406">Factored</a></td><td class="nobr">Jan. 16, 2013</td><td>P84 * P143</td></tr>
<tr><td class="num">11,301-</td><td class="nobr">SNFS 268.7</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=405">Factored</a></td><td class="nobr">Jan. 18, 2013</td><td>P106 * P156</td></tr>
<tr><td class="num">11,301+</td><td class="nobr">SNFS 268.7</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=400">Factored</a></td><td class="nobr">Dec. 15, 2012</td><td>P104 * P107</td></tr>
<tr><td class="num">2,1037-</td><td class="nobr">SNFS 312.5</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=409">Factored</a></td><td class="nobr">Jan. 29, 2013</td><td>P67 * P86 * P137</td></tr>
<tr><td class="num">1723,83-</td><td class="nobr">SNFS 271.8</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=394">Factored</a></td><td class="nobr">Nov. 26, 2012</td><td>P87 * P180</td></tr>
<tr><td class="num">3,725+</td><td class="nobr">GNFS 179.4</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=387">Factored</a></td><td class="nobr">Oct. 14, 2012</td><td>P79 * P102</td></tr>
<tr><td class="num">3,637+</td><td class="nobr">SNFS 260.5</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=382">Factored</a></td><td class="nobr">Oct. 8, 2012</td><td>P63 * P66 * P109</td></tr>
<tr><td class="num">3,637-</td><td class="nobr">SNFS 260.5</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=377">Factored</a></td><td class="nobr">Sept. 22, 2012</td><td>P85 * P127</td></tr>
<tr><td class="num"><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=366">TAOCP</a></td><td class="nobr">GNFS 186.8</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=373">Factored</a></td><td class="nobr">Sept. 14, 2012</td><td>P91 * P97</td></tr>
<tr><td class="num">3,635+</td><td class="nobr">SNFS 242.4</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=371">Factored</a></td><td class="nobr">Aug. 22, 2012</td><td>P94 * P99</td></tr>
<tr><td class="num">3,625-</td><td class="nobr">SNFS 238.6</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=361">Factored</a></td><td class="nobr">July 27, 2012</td><td>P72 * P135</td></tr>
<tr><td class="num">197,113-</td><td class="nobr">SNFS 261.6</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=356">Factored</a></td><td class="nobr">July 7, 2012</td><td>P117 * P141</td></tr>
<tr><td class="num">59,149-</td><td class="nobr">SNFS 265.6</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=351">Factored</a></td><td class="nobr">June 29, 2012</td><td>P101 * P162</td></tr>
<tr><td class="num"><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=344">B200</a></td><td class="nobr">GNFS 203.5</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=367">Factored</a></td><td class="nobr">Aug. 2, 2012</td><td>P90 * P115</td></tr>
<tr><td class="num">2,1019-</td><td class="nobr">SNFS 307.1</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=385">Factored</a></td><td class="nobr">Oct. 12, 2012</td><td>P76 * P171</td></tr>
<tr><td class="num">5,433+</td><td class="nobr">SNFS 302.7</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=338">Factored</a></td><td class="nobr">Apr. 25, 2012</td><td>P133 * P139</td></tr>
<tr><td class="num">7,365-</td><td class="nobr">SNFS 246.8</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=353">Factored</a></td><td class="nobr">June 30, 2012</td><td>P97 * P125</td></tr>
<tr><td class="num">10,305+</td><td class="nobr">SNFS 244.0</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=345">Factored</a></td><td class="nobr">May 27, 2012</td><td>P75 * P135</td></tr>
<tr><td class="num">11,290+</td><td class="nobr">SNFS 241.6</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=341">Factored</a></td><td class="nobr">Apr. 28, 2012</td><td>P91 * P110</td></tr>
<tr><td class="num">2,1000+</td><td class="nobr">SNFS 240.8</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=334">Factored</a></td><td class="nobr">Apr. 7, 2012</td><td>P67 * P151</td></tr>
<tr><td class="num">7,355+</td><td class="nobr">SNFS 240.0</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=331">Factored</a></td><td class="nobr">Mar. 21, 2012</td><td>P80 * P126</td></tr>
<tr><td class="num">2,1990L</td><td class="nobr">SNFS 239.6</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=326">Factored</a></td><td class="nobr">Feb. 26, 2012</td><td>P109 * P126</td></tr>
<tr><td class="num">2,2382M</td><td class="nobr">SNFS 239.0</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=324">Factored</a></td><td class="nobr">Jan. 29, 2012</td><td>P100 * P119</td></tr>
<tr><td class="num">10,590M</td><td class="nobr">SNFS 238.0</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=322">Factored</a></td><td class="nobr">Jan. 1, 2012</td><td>P101 * P112</td></tr>
<tr><td class="num">3,605+</td><td class="nobr">SNFS 230.9</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=317">Factored</a></td><td class="nobr">Dec. 3, 2011</td><td>P76 * P109</td></tr>
<tr><td class="num">2,1822M</td><td class="nobr">SNFS 274.5</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=314">Factored</a></td><td class="nobr">Nov. 27, 2011</td><td>P85 * P131</td></tr>
<tr><td class="num">10,274+</td><td class="nobr">SNFS 275.4</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=311">Factored</a></td><td class="nobr">Oct. 21, 2011</td><td>P77 * P88 * P90</td></tr>
<tr><td class="num">11,263+</td><td class="nobr">SNFS 274.9</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=308">Factored</a></td><td class="nobr">Sept. 9, 2011</td><td>P78 * P174</td></tr>
<tr><td class="num">2,1814M</td><td class="nobr">SNFS 273.0</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=301">Factored</a></td><td class="nobr">July 27, 2011</td><td>P98 * P137</td></tr>
<tr><td class="num">7,323+</td><td class="nobr">SNFS 273.8</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=299">Factored</a></td><td class="nobr">June 24, 2011</td><td>P65 * P164</td></tr>
<tr><td class="num">7,323-</td><td class="nobr">SNFS 273.8</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=297">Factored</a></td><td class="nobr">June 13, 2011</td><td>P92 * P150</td></tr>
<tr><td class="num">2,2114M</td><td class="nobr">SNFS 272.7</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=295">Factored</a></td><td class="nobr">June 5, 2011</td><td>P73 * P126</td></tr>
<tr><td class="num">2,1061-</td><td class="nobr">SNFS 319.7</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=364">Factored</a></td><td class="nobr">Aug. 4, 2012</td><td>P143 * P177</td></tr>
<tr><td class="num">6,374+</td><td class="nobr">SNFS 264.6</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=290">Factored</a></td><td class="nobr">May 23, 2011</td><td>P104 * P111</td></tr>
<tr><td class="num">6,349-</td><td class="nobr">SNFS 271.6</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=289">Factored</a></td><td class="nobr">May 13, 2011</td><td>P68 * P141</td></tr>
<tr><td class="num">7,341+</td><td class="nobr">SNFS 262.0</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=292">Factored</a></td><td class="nobr">May 29, 2011</td><td>P75 * P134</td></tr>
<tr><td class="num">2,979+</td><td class="nobr">SNFS 267.9</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=285">Factored</a></td><td class="nobr">Apr. 18, 2011</td><td>P101 * P155</td></tr>
<tr><td class="num">5,389-</td><td class="nobr">SNFS 272.6</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=282">Factored</a></td><td class="nobr">Apr. 4, 2011</td><td>P79 * P152</td></tr>
<tr><td class="num">3,569-</td><td class="nobr">SNFS 272.0</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=278">Factored</a></td><td class="nobr">Mar. 17, 2011</td><td>P115 * P116</td></tr>
<tr><td class="num">2,1031-</td><td class="nobr">SNFS 310.7</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=304">Factored</a></td><td class="nobr">Aug. 9, 2011</td><td>P74 * P225</td></tr>
<tr><td class="num">2,997+</td><td class="nobr">SNFS 300.1</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=269">Factored</a></td><td class="nobr">Feb. 13, 2011</td><td>P103 * P184</td></tr>
<tr><td class="num">6,379+</td><td class="nobr">SNFS 294.9</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=248">Factored</a></td><td class="nobr">Nov. 29, 2010</td><td>P62 * P208</td></tr>
<tr><td class="num">3,607-</td><td class="nobr">SNFS 289.6</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=238">Factored</a></td><td class="nobr">Nov. 1, 2010</td><td>P85 * P96 * P110</td></tr>
<tr><td class="num">6,377-</td><td class="nobr">SNFS 270.8</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=275">Factored</a></td><td class="nobr">Mar. 1, 2011</td><td>P64 * P77 * P109</td></tr>
<tr><td class="num">2,1040+</td><td class="nobr">GNFS 183.7</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=268">Factored</a></td><td class="nobr">Feb. 13, 2011</td><td>P82 * P103</td></tr>
<tr><td class="num">2,1099+</td><td class="nobr">GNFS 181.7</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=266">Factored</a></td><td class="nobr">Jan. 28, 2011</td><td>P71 * P112</td></tr>
<tr><td class="num">6,347+</td><td class="nobr">SNFS 270.8</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=264">Factored</a></td><td class="nobr">Jan. 16, 2011</td><td>P95 * P101</td></tr>
<tr><td class="num">5,386+</td><td class="nobr">SNFS 269.8</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=261">Factored</a></td><td class="nobr">Jan. 8, 2011</td><td>P76 * P147</td></tr>
<tr><td class="num">5,895M</td><td class="nobr">GNFS 180.9</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=256">Factored</a></td><td class="nobr">Dec. 20, 2010</td><td>P80 * P101</td></tr>
<tr><td class="num">2,1195+</td><td class="nobr">GNFS 178.6</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=246">Factored</a></td><td class="nobr">Nov. 28, 2010</td><td>P70 * P110</td></tr>
<tr><td class="num">2,2086L</td><td class="nobr">SNFS 269.1</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=243">Factored</a></td><td class="nobr">Nov. 17, 2010</td><td>P55 * P149</td></tr>
<tr><td class="num">5,409-</td><td class="nobr">SNFS 285.9</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=234">Factored</a></td><td class="nobr">Oct. 4, 2010</td><td>P96 * P186</td></tr>
<tr><td class="num">12,254+</td><td class="nobr">SNFS 274.7</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=218">Factored</a></td><td class="nobr">Aug. 18, 2010</td><td>P97 * P134</td></tr>
<tr><td class="num">6,346+</td><td class="nobr">SNFS 270.2</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=239">Factored</a></td><td class="nobr">Nov. 1, 2010</td><td>P95 * P147</td></tr>
<tr><td class="num">7,338+</td><td class="nobr">SNFS 263.7</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=229">Factored</a></td><td class="nobr">Sept. 27, 2010</td><td>P80 * P156</td></tr>
<tr><td class="num">3,563+</td><td class="nobr">SNFS 269.1</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=227">Factored</a></td><td class="nobr">Sept. 17, 2010</td><td>P117 * P123</td></tr>
<tr><td class="num">3,563-</td><td class="nobr">SNFS 269.1</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=220">Factored</a></td><td class="nobr">Aug. 22, 2010</td><td>P83 * P117</td></tr>
<tr><td class="num">5,448+</td><td class="nobr">SNFS 268.4</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=215">Factored</a></td><td class="nobr">Aug. 6, 2010</td><td>P65 * P141</td></tr>
<tr><td class="num">2,1036+</td><td class="nobr">SNFS 267.3</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=209">Factored</a></td><td class="nobr">July 27, 2010</td><td>P71 * P142</td></tr>
<tr><td class="num">7,364+</td><td class="nobr">SNFS 263.7</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=207">Factored</a></td><td class="nobr">July 16, 2010</td><td>P73 * P152</td></tr>
<tr><td class="num">2,904+</td><td class="nobr">SNFS 272.7</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=213">Factored</a></td><td class="nobr">July 31, 2010</td><td>P81 * P130</td></tr>
<tr><td class="num">11,287-</td><td class="nobr">SNFS 256.2</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=205">Factored</a></td><td class="nobr">July 2, 2010</td><td>P68 * P118</td></tr>
<tr><td class="num">11,287+</td><td class="nobr">SNFS 256.2</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=203">Factored</a></td><td class="nobr">June 30, 2010</td><td>P64 * P71 * P100</td></tr>
<tr><td class="num">10,387-</td><td class="nobr">SNFS 258.0</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=201">Factored</a></td><td class="nobr">June 28, 2010</td><td>P97 * P125</td></tr>
<tr><td class="num">10,387+</td><td class="nobr">SNFS 258.0</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=197">Factored</a></td><td class="nobr">June 20, 2010</td><td>P80 * P143</td></tr>
<tr><td class="num">10,384+</td><td class="nobr">SNFS 258.0</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=195">Factored</a></td><td class="nobr">June 17, 2010</td><td>P81 * P131</td></tr>
<tr><td class="num">2,1798L</td><td class="nobr">SNFS 270.9</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=199">Factored</a></td><td class="nobr">June 27, 2010</td><td>P62 * P146</td></tr>
<tr><td class="num">6,385-</td><td class="nobr">SNFS 256.8</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=191">Factored</a></td><td class="nobr">June 7, 2010</td><td>P84 * P103</td></tr>
<tr><td class="num">5,427-</td><td class="nobr">SNFS 255.8</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=187">Factored</a></td><td class="nobr">May 29, 2010</td><td>P70 * P135</td></tr>
<tr><td class="num">5,427+</td><td class="nobr">SNFS 255.8</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=184">Factored</a></td><td class="nobr">May 23, 2010</td><td>P54 * P145</td></tr>
<tr><td class="num">3,583+</td><td class="nobr">SNFS 252.9</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=176">Factored</a></td><td class="nobr">May 4, 2010</td><td>P76 * P149</td></tr>
<tr><td class="num">3,562+</td><td class="nobr">SNFS 269.1</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=189">Factored</a></td><td class="nobr">June 3, 2010</td><td>P86 * P170</td></tr>
<tr><td class="num">10,272+</td><td class="nobr">SNFS 272.0</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=193">Factored</a></td><td class="nobr">June 14, 2010</td><td>P98 * P114</td></tr>
<tr><td class="num">2,1774M</td><td class="nobr">SNFS 267.3</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=182">Factored</a></td><td class="nobr">May 18, 2010</td><td>P103 * P113</td></tr>
<tr><td class="num">2,1766L</td><td class="nobr">SNFS 265.8</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=174">Factored</a></td><td class="nobr">May 3, 2010</td><td>P122 * P137</td></tr>
<tr><td class="num">2,1762M</td><td class="nobr">SNFS 265.5</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=171">Factored</a></td><td class="nobr">Apr. 24, 2010</td><td>P88 * P146</td></tr>
<tr><td class="num">2,1762L</td><td class="nobr">SNFS 265.5</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=167">Factored</a></td><td class="nobr">Apr. 15, 2010</td><td>P83 * P166</td></tr>
<tr><td class="num">2,1754M</td><td class="nobr">SNFS 264.0</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=157">Factored</a></td><td class="nobr">Apr. 1, 2010</td><td>P79 * P102</td></tr>
<tr><td class="num">2,1718M</td><td class="nobr">SNFS 258.6</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=151">Factored</a></td><td class="nobr">Mar. 20, 2010</td><td>P92 * P108</td></tr>
<tr><td class="num">2,1718L</td><td class="nobr">SNFS 258.6</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=147">Factored</a></td><td class="nobr">Mar. 13, 2010</td><td>P96 * P137</td></tr>
<tr><td class="num">11,257-</td><td class="nobr">SNFS 268.7</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=160">Factored</a></td><td class="nobr">Apr. 6, 2010</td><td>P61 * P71 * P94</td></tr>
<tr><td class="num">5,383-</td><td class="nobr">SNFS 268.4</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=169">Factored</a></td><td class="nobr">Apr. 17, 2010</td><td>P88 * P96</td></tr>
<tr><td class="num">11,254+</td><td class="nobr">SNFS 264.5</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=149">Factored</a></td><td class="nobr">Mar. 20, 2010</td><td>P68 * P160</td></tr>
<tr><td class="num">5,377+</td><td class="nobr">SNFS 243.2</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=130">Factored</a></td><td class="nobr">Feb. 5, 2010</td><td>P79 * P93</td></tr>
<tr><td class="num">6,338+</td><td class="nobr">SNFS 242.8</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=128">Factored</a></td><td class="nobr">Feb. 4, 2010</td><td>P82 * P83</td></tr>
<tr><td class="num">10,271-</td><td class="nobr">SNFS 271.0</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=162">Factored</a></td><td class="nobr">Apr. 10, 2010</td><td>P94 * P121</td></tr>
<tr><td class="num">2,1714M</td><td class="nobr">GNFS 167.8</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=124">Factored</a></td><td class="nobr">Jan. 30, 2010</td><td>P64 * P105</td></tr>
<tr><td class="num">2,1714L</td><td class="nobr">SNFS 258.3</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=136">Factored</a></td><td class="nobr">Feb. 15, 2010</td><td>P78 * P114</td></tr>
<tr><td class="num"><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=102">EM43</a></td><td class="nobr">GNFS 179.4</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=102#398">Factored</a></td><td class="nobr">Mar. 9, 2010</td><td>P68 * P112</td></tr>
<tr><td class="num">2,899-</td><td class="nobr">SNFS 270.9</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=165">Factored</a></td><td class="nobr">Apr. 14, 2010</td><td>P91 * P95</td></tr>
<tr><td class="num">2,887-</td><td class="nobr">SNFS 267.3</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=134">Factored</a></td><td class="nobr">Feb. 11, 2010</td><td>P73 * P137</td></tr>
<tr><td class="num">2,887+</td><td class="nobr">SNFS 267.3</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=142">Factored</a></td><td class="nobr">Mar. 5, 2010</td><td>P83 * P124</td></tr>
<tr><td class="num">7,314+</td><td class="nobr">SNFS 265.4</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=145">Factored</a></td><td class="nobr">Mar. 13, 2010</td><td>P66 * P150</td></tr>
<tr><td class="num">7,311-</td><td class="nobr">SNFS 263.7</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=122">Factored</a></td><td class="nobr">Jan. 28, 2010</td><td>P66 * P160</td></tr>
<tr><td class="num">10,268+</td><td class="nobr">SNFS 269.4</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=126">Factored</a></td><td class="nobr">Jan. 31, 2010</td><td>P63 * P181</td></tr>
<tr><td class="num">3,548+</td><td class="nobr">SNFS 261.5</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=120">Factored</a></td><td class="nobr">Jan. 24, 2010</td><td>P58 * P139</td></tr>
<tr><td class="num">5,373+</td><td class="nobr">SNFS 260.7</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=118">Factored</a></td><td class="nobr">Jan. 18, 2010</td><td>P101 * P114</td></tr>
<tr><td class="num">6,332+</td><td class="nobr">SNFS 258.3</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=113">Factored</a></td><td class="nobr">Jan. 8, 2010</td><td>P69 * P108</td></tr>
<tr><td class="num">6,331+</td><td class="nobr">SNFS 257.6</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=110">Factored</a></td><td class="nobr">Jan. 7, 2010</td><td>P68 * P160</td></tr>
<tr><td class="num">5,361+</td><td class="nobr">SNFS 252.3</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=60">Factored</a></td><td class="nobr">Dec. 16, 2009</td><td>P62 * P159</td></tr>
<tr><td class="num">6,323+</td><td class="nobr">SNFS 252.1</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=108">Factored</a></td><td class="nobr">Dec. 31, 2009</td><td>P106 * P115</td></tr>
<tr><td class="num">2,1678M</td><td class="nobr">SNFS 252.9</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=59">Factored</a></td><td class="nobr">Dec. 12, 2009</td><td>P64 * P133</td></tr>
<tr><td class="num">2,1678L</td><td class="nobr">SNFS 252.9</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=57">Factored</a></td><td class="nobr">Dec. 9, 2009</td><td>P98 * P99</td></tr>
<tr><td class="num">5,361-</td><td class="nobr">SNFS 252.3</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=54">Factored</a></td><td class="nobr">Dec. 6, 2009</td><td>P71 * P118</td></tr>
<tr><td class="num">6,334+</td><td class="nobr">SNFS 261.5</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=53">Factored</a></td><td class="nobr">Dec. 6, 2009</td><td>P80 * P101</td></tr>
<tr><td class="num">10,269-</td><td class="nobr">SNFS 270.0</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=63">Factored</a></td><td class="nobr">Dec. 20, 2009</td><td>P91 * P143</td></tr>
<tr><td class="num">2,1726L</td><td class="nobr">SNFS 260.1</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=56">Factored</a></td><td class="nobr">Dec. 7, 2009</td><td>P108 * P109</td></tr>
<tr><td class="num">7,307+</td><td class="nobr">SNFS 259.4</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=47">Factored</a></td><td class="nobr">Nov. 19, 2009</td><td>P89 * P137</td></tr>
<tr><td class="num">12,239-</td><td class="nobr">SNFS 259.0</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=51">Factored</a></td><td class="nobr">Nov. 25, 2009</td><td>P101 * P102</td></tr>
<tr><td class="num">2,859+</td><td class="nobr">SNFS 258.6</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=48">Factored</a></td><td class="nobr">Nov. 21, 2009</td><td>P83 * P151</td></tr>
<tr><td class="num">3,538+</td><td class="nobr">SNFS 257.6</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=41">Factored</a></td><td class="nobr">Nov. 4, 2009</td><td>P77 * P101</td></tr>
<tr><td class="num">2,863-</td><td class="nobr">SNFS 260.1</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=50">Factored</a></td><td class="nobr">Nov. 22, 2009</td><td>P97 * P137</td></tr>
<tr><td class="num">2,856+</td><td class="nobr">SNFS 258.3</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=43">Factored</a></td><td class="nobr">Nov. 14, 2009</td><td>P69 * P155</td></tr>
<tr><td class="num">2,853+</td><td class="nobr">SNFS 256.8</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=42">Factored</a></td><td class="nobr">Nov. 7, 2009</td><td>P101 * P121</td></tr>
<tr><td class="num">2,851+</td><td class="nobr">SNFS 256.5</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=39">Factored</a></td><td class="nobr">Oct. 31, 2009</td><td>P93 * P121</td></tr>
<tr><td class="num">12,233-</td><td class="nobr">SNFS 252.5</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=27">Factored</a></td><td class="nobr">Oct. 20, 2009</td><td>P64 * P151</td></tr>
<tr><td class="num">12,232+</td><td class="nobr">SNFS 251.3</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=23">Factored</a></td><td class="nobr">Oct. 15, 2009</td><td>P61 * P120</td></tr>
<tr><td class="num">6,317+</td><td class="nobr">SNFS 247.5</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=17">Factored</a></td><td class="nobr">Oct. 1, 2009</td><td>P68 * P158</td></tr>
<tr><td class="num">6,316+</td><td class="nobr">SNFS 247.5</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=20">Factored</a></td><td class="nobr">Oct. 5, 2009</td><td>P101 * P110</td></tr>
<tr><td class="num">5,353+</td><td class="nobr">SNFS 247.4</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=18">Factored</a></td><td class="nobr">Oct. 2, 2009</td><td>P94 * P137</td></tr>
<tr><td class="num">2,2214L</td><td class="nobr">SNFS 222.2</td><td><a href="https://escatter11.fullerton.edu/nfs/forum_thread.php?id=7">Factored</a></td><td class="nobr">Sept. 11, 2009</td><td>P87 * P104</td></tr>
EOT;

page_head(tra("Status of numbers processed by NFS@Home"));

$done = substr_count($historical, '<tr>')
    + count(BoincNumber_f::enum("status=4"));

echo "<p>".tra("%1 numbers factored to date.", "<strong>$done</strong>")."</p>\n";

start_table('table-striped');
row_heading_array(
    array(
        tra("Number"),
        tra("Difficulty"),
        tra("Status"),
        tra("Completion date"),
        tra("Size of factors"),
    ),
    array_fill(0, 5, 'scope="col"')
);

display_result(BoincNumber_f::enum("status=0"), tra("Queued"));
display_result(BoincNumber_f::enum("status=1"), tra("Sieving"));
display_result(BoincNumber_f::enum("status=2"), tra("Processing"));
display_result(BoincNumber_f::enum("status=3"), tra("Processing"));
display_result(BoincNumber_f::enum("status=4"), tra("Factored"));

echo $historical."\n";

end_table();
page_tail();

?>
