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

require_once("../inc/db.inc");
require_once("../inc/util.inc");
require_once("../inc/news.inc");
require_once("../inc/cache.inc");
require_once("../inc/uotd.inc");
require_once("../inc/sanitize_html.inc");
require_once("../inc/translation.inc");
require_once("../inc/text_transform.inc");
require_once("../project/project.inc");
// require_once("../project/project_news.inc");


function show_nav() {
//    $config = get_config();
//    $master_url = parse_config($config, "<master_url>");
    echo "<div id=\"mainnav\">
        <h2>About ".PROJECT."</h2>
        NFS@Home is a research project that uses Internet-connected
        computers to do the lattice sieving step in the Number Field Sieve factorization of large integers.
        As a young school student, you gained your first experience at breaking an integer into prime factors, such as 15 = 3 * 5 or 35 = 5 * 7.  NFS@Home is a continuation of that experience, only with integers that are hundreds of digits long. 
        Most recent large factorizations have been done primarily by large clusters at universities. With NFS@Home you can participate in state-of-the-art factorizations simply by downloading and running a free program on your computer.
        <p>
        Integer factorization is interesting from both mathematical and practical perspectives. Mathematically, for instance, the calculation of <a href=\"http://en.wikipedia.org/wiki/Multiplicative_function\">multiplicative functions</a> in number theory for a particular number require the factors of the number. Likewise, the integer factorization of particular numbers can aid in the proof that an associated number is prime. Practically, many public key algorithms, including the <a href=\"http://en.wikipedia.org/wiki/RSA\">RSA algorithm</a>, rely on the fact that the publicly available modulus cannot be factored. If it is factored, the private key can be easily calculated. Until quite recently, RSA-512, which uses a 512-bit modulus (155 digits), was commonly used but can now be easily broken.
        <p>
        Many of the numbers that we are factoring are chosen from the <a href=\"http://homes.cerias.purdue.edu/~ssw/cun/index.html\">Cunningham project</a>. Started in 1925, it is one of the oldest continuously ongoing projects in computational number theory.  The third edition of the book, published by the American Mathematical Society in 2002, is available as a <a href=\"http://www.ams.org/online_bks/conm22/\">free download</a>. All results obtained since, including those of NFS@Home, are available on the Cunningham project website.
        <p> NFS@Home is hosted at <a href=\"http://www.fullerton.edu\">California State University Fullerton</a>, and is supported in part by the <a href=\"http://www.nsf.gov\">National Science Foundation</a> through <a href=\"https://access-ci.org\">ACCESS</a> resources provided by the <a href=\"http://www.tacc.utexas.edu\">Texas Advanced Computing Center</a>, the <a href=\"http://www.sdsc.edu\">San Diego Supercomputer Center</a>, the <a href=\"http://www.ncsa.illinois.edu\">National Center for Supercomputing Applications</a>, and <a href=\"http://www.rcac.purdue.edu/\">Purdue University</a> under grant number DMS100027.
        <p><h2>Join ".PROJECT."</h2>
        <ul>
        <li><a href=\"info.php\">".tra("Read our rules and policies")."</a>
        <li> This project uses BOINC.
            If you're already running BOINC, select Attach to Project.
            If not, <a href=\"signup.php\">sign up</a> or <a target=\"_new\" href=\"http://boinc.berkeley.edu/download.php\">download BOINC</a>.
        <li> When prompted, select <b>".PROJECT."</b> from the list of projects.
        <li> If you're running a command-line or pre-5.0 version of BOINC,
            <a href=\"create_account_form.php\">create an account</a> first.
        <li> If you have any problems,
            <a target=\"_new\" href=\"http://boinc.berkeley.edu/help.php\">get help here</a>.
        <li> <a href=\"crunching.php\">Detailed status of lasieved</a>
        <li> <a href=\"crunching_es.php\">Detailed status of lasievee_small</a>
        <li> <a href=\"crunching_e.php\">Detailed status of lasievee</a>
        <li> <a href=\"crunching_fs.php\">Detailed status of lasievef_small</a>
        <li> <a href=\"crunching_cs.php\">Detailed status of cudasieve</a>
        <li> <a href=\"server_status.php\">Server status</a>
        </ul>

        <h2>Returning participants</h2>
        <ul>
        <li><a href=\"home.php\">Your account</a> - view stats, modify preferences
        <li><a href=\"team.php\">Teams</a> - create or join a team
        <li><a href=\"cert1.php\">Certificate</a>
        <li> <a href=\"apps.php\">".tra("Applications")."</a>

        </ul>
        <h2>".tra("Community")."</h2>
        <ul>
        <li><a href=\"profile_menu.php\">".tra("Profiles")."</a>
        <li><a href=\"user_search.php\">User search</a>
        <li><a href=\"forum_index.php\">".tra("Message boards")."</a>
        <!-- <li><a href=\"forum_help_desk.php\">".tra("Questions and Answers")."</a> -->
        <li><a href=\"numbers.php\">Status of Numbers</a>
        <li><a href=\"stats.php\">Statistics</a>
        <li><a href=language_select.php>Languages</a>
        <li><a href=\"contributions.html\">Special contributions</a>
        </ul>
        </div>
    ";
}

$caching = false;

if ($caching) {
    start_cache(INDEX_PAGE_TTL);
}

$stopped = web_stopped();
$rssname = PROJECT . " RSS 2.0" ;
$rsslink = URL_BASE . "rss_main.php";

$charset = tra("CHARSET");

// if ($charset != "CHARSET") {
    header("Content-type: text/html; charset=utf-8");
// }

echo "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">";

echo "<html lang=\"en-US\">
    <head>
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">
    <title>".PROJECT."</title>
	<link rel=\"stylesheet\" type=\"text/css\" href=\"main.css\" media=\"all\" />
    <link rel=\"stylesheet\" type=\"text/css\" href=\"".STYLESHEET."\">
    <link rel=\"alternate\" type=\"application/rss+xml\" title=\"".$rssname."\" href=\"".$rsslink."\">
";
include 'schedulers.txt';
echo "
    </head><body>
    <!-- <span class=page_title>".PROJECT."</span> -->
    <center><span class=\"page_title\"><img src=\"img/NFS_Logo.jpg\" alt=\"NFS@Home\" height=\"150\" width=\"600\"></span></center>
    <table cellpadding=\"8\" cellspacing=\"4\">
    <tr><td rowspan=\"2\" valign=\"top\" width=\"40%\">
";

if ($stopped) {
    echo "
        <b>".PROJECT." is temporarily shut down for maintenance.
        Please try again later</b>.
    ";
} else {
    db_init();
    show_nav();
}

echo "
    <p><a href=\"http://www.nsf.gov/\"><img align=\"middle\" border=\"0\" src=\"img/nsf.gif\" alt=\"NSF Logo\"></a>
    <a href=\"https://access-ci.org/\"><img align=\"middle\" border=\"0\" width=\"300px\" src=\"img/access-logo-footer.svg\" alt=\"Access Logo\"></a>
    </p><p>
    <a href=\"http://boinc.berkeley.edu/\"><img align=\"middle\" border=\"0\" src=\"img/pb_boinc.gif\" alt=\"Powered by BOINC\"></a>
    </p><p>
    <a href=\"http://www.fullerton.edu/\"><img align=\"middle\" border=\"0\" width=\"350px\" src=\"img/CalStateFullerton-color.png\" alt=\"California State University Fullerton\"></a>
    </p>
    </td>
";

if (!$stopped) {
    $profile = get_current_uotd();
    if ($profile) {
        echo "
            <td id=\"uotd\">
            <h2>".tra("User of the day")."</h2>
        ";
        show_uotd($profile);
        echo "</td></tr>\n";
    }
}

echo "
    <tr><td id=\"news\">
    <h2>News</h2>
";
// show_news($project_news, 5);
show_news(0, 5);
// if (count($project_news) > 5) {
//     echo "<a href=\"old_news.php\">...more</a>";
// }
echo "
    </td>
    </tr></table>
";


if ($caching) {
    page_tail_main(true);
    end_cache(INDEX_PAGE_TTL);
} else {
    page_tail_main();
}

?>
