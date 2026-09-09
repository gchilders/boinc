<?php
// Modern front-page preview for NFS@Home.
//
// Lives alongside the current index.php as html/user/index2.php and is
// reachable at https://escatter11.fullerton.edu/nfs/index2.php
// Nothing here touches index.php, main.css or white.css.
//
// It links only nfs2.css, so this page does not inherit the old styling.
//
// When you are happy with it: cp index2.php index.php
// (schedulers.txt is already included below, so the copy is safe to serve as
// the master page.)

require_once("../inc/db.inc");
require_once("../inc/util.inc");
require_once("../inc/news.inc");
require_once("../inc/cache.inc");
require_once("../inc/uotd.inc");
require_once("../inc/sanitize_html.inc");
require_once("../inc/translation.inc");
require_once("../inc/text_transform.inc");
require_once("../project/project.inc");

// The sieving band reads html/user/sieve_status.json, written every few
// minutes by html/cron/update_sieve_status.php. No database work happens on
// this page: it is the master URL and every BOINC client fetches it.
require_once(dirname(__FILE__)."/sieve_status.inc");

$stopped = web_stopped();
$rssname = PROJECT . " RSS 2.0";
$rsslink = URL_BASE . "rss_main.php";

header("Content-type: text/html; charset=utf-8");

if (!$stopped) {
    db_init();
}

// Needed for the right-hand side of the navbar. Returns null when nobody is
// logged in, and guards on web_stopped() internally, so it is safe either
// way. Passing false is what stops it redirecting to the login form.
$user = $stopped ? null : get_logged_in_user(false);

function h($s) {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en-US" data-theme="auto">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo h(PROJECT); ?></title>
<meta name="description" content="<?php echo h(PROJECT); ?> uses Internet-connected computers to do the lattice sieving step in the Number Field Sieve factorization of large integers.">
<!-- Preview page: keep search engines off it so it is not indexed as a
     duplicate of the real front page. Delete this line when promoting. -->
<meta name="robots" content="noindex, nofollow">
<!-- Same three stylesheets, in the same order, that page_head() emits on
     every other page. Keeping the order identical is what lets nfs2.css be
     the single site theme. -->
<link rel="stylesheet" type="text/css" href="sample_bootstrap.min.css" media="all">
<link rel="stylesheet" type="text/css" href="custom.css" media="all">
<link rel="stylesheet" type="text/css" href="nfs2.css" media="all">
<!-- Loaded in <head> so the stored theme is applied before first paint. -->
<script src="nfs2-theme.js"></script>
<link rel="alternate" type="application/rss+xml" title="<?php echo h($rssname); ?>" href="<?php echo h($rsslink); ?>">
<?php
// Required on the master page: the BOINC client parses these commented
// <scheduler> elements. Harmless here, and it means promoting this file to
// index.php is a straight copy.
include 'schedulers.txt';
?>
</head>
<body>
<a class="skip-link" href="#main">Skip to main content</a>

<div class="masthead">
  <div class="masthead-inner">
    <h1 class="site-title">
      <img class="site-logo" src="img/NFS_Logo.jpg" alt="<?php echo h(PROJECT); ?>" width="600" height="150">
    </h1>
    <p class="site-tagline">Volunteer computing for the Number Field Sieve, hosted at Cal State Fullerton</p>
    <div class="theme-switch">
      <span class="theme-switch-label">Theme</span>
      <button type="button" class="theme-btn" data-theme-set="auto" aria-pressed="true">Auto</button>
      <button type="button" class="theme-btn" data-theme-set="light" aria-pressed="false">Light</button>
      <button type="button" class="theme-btn" data-theme-set="dark" aria-pressed="false">Dark</button>
    </div>
  </div>
</div>

<nav class="navbar navbar-default" aria-label="Main">
  <div class="container-fluid">
    <div class="navbar-header">
      <button type="button" class="navbar-toggle" data-toggle="collapse"
              data-target="#myNavbar" aria-controls="myNavbar" aria-expanded="false">
        <span class="sr-only">Toggle navigation</span>
        <span class="icon-bar"></span>
        <span class="icon-bar"></span>
        <span class="icon-bar"></span>
      </button>
    </div>
    <div class="collapse navbar-collapse" id="myNavbar">
      <ul class="nav navbar-nav">
        <li class="active"><a href="#about">About</a></li>
        <li class="dropdown">
          <a class="dropdown-toggle" data-toggle="dropdown" href="#" role="button"
             aria-haspopup="true" aria-expanded="false">Status <span class="caret"></span></a>
          <ul class="dropdown-menu">
            <li><a href="crunching.php">Detailed status of lasieved</a></li>
            <li><a href="crunching_es.php">Detailed status of lasievee_small</a></li>
            <li><a href="crunching_e.php">Detailed status of lasievee</a></li>
            <li><a href="crunching_fs.php">Detailed status of lasievef_small</a></li>
            <li><a href="crunching_myf.php">Detailed status of lasievef</a></li>
            <li><a href="crunching_cs.php">Detailed status of cudasieve</a></li>
            <li role="separator" class="divider"></li>
            <li><a href="numbers.php">Status of numbers</a></li>
            <li><a href="server_status.php">Server status</a></li>
          </ul>
        </li>
        <li class="dropdown">
          <a class="dropdown-toggle" data-toggle="dropdown" href="#" role="button"
             aria-haspopup="true" aria-expanded="false"><?php echo tra("Community"); ?> <span class="caret"></span></a>
          <ul class="dropdown-menu">
            <li><a href="forum_index.php"><?php echo tra("Message boards"); ?></a></li>
            <li><a href="profile_menu.php"><?php echo tra("Profiles"); ?></a></li>
            <li><a href="team.php">Teams</a></li>
            <li><a href="user_search.php">User search</a></li>
            <li><a href="stats.php">Statistics</a></li>
            <li><a href="contributions.html">Special contributions</a></li>
          </ul>
        </li>
        <li><a href="apps.php"><?php echo tra("Applications"); ?></a></li>
<?php
      // navbar_right() closes the list above, opens the right-hand one and
      // fills it from login state: the user's name plus a tokenised Log out
      // link when signed in, Join and Login when not. Calling BOINC's own
      // function rather than hardcoding it means this page cannot drift out
      // of step with every other page's navbar.
      navbar_right($user);

      // Closes the right-hand <ul>, the collapse div, the container-fluid
      // div and the <nav>. Do not close them by hand as well.
      navbar_end();
?>

<?php if ($stopped) { ?>
<section class="sieve">
  <div class="container-fluid">
    <p class="server-line" style="padding-top:1.5rem">
      <strong><?php echo tra("%1 is temporarily shut down for maintenance.", PROJECT); ?></strong>
    </p>
  </div>
</section>
<?php } else { show_sieve_band(); } ?>

<main id="main">
  <div class="container-fluid">
    <div class="row">

      <div class="col-md-8">

        <section class="prose" id="about">
          <h2>About <?php echo h(PROJECT); ?></h2>
          <p>
            NFS@Home is a research project that uses Internet-connected computers to do
            the lattice sieving step in the Number Field Sieve factorization of large
            integers. As a young school student, you gained your first experience at
            breaking an integer into prime factors, such as 15 = 3 * 5 or 35 = 5 * 7.
            NFS@Home is a continuation of that experience, only with integers that are
            hundreds of digits long. Most recent large factorizations have been done
            primarily by large clusters at universities. With NFS@Home you can
            participate in state-of-the-art factorizations simply by downloading and
            running a free program on your computer.
          </p>
          <p>
            Integer factorization is interesting from both mathematical and practical
            perspectives. Mathematically, for instance, the calculation of
            <a href="http://en.wikipedia.org/wiki/Multiplicative_function">multiplicative
            functions</a> in number theory for a particular number require the factors of
            the number. Likewise, the integer factorization of particular numbers can aid
            in the proof that an associated number is prime. Practically, many public key
            algorithms, including the <a href="http://en.wikipedia.org/wiki/RSA">RSA
            algorithm</a>, rely on the fact that the publicly available modulus cannot be
            factored. If it is factored, the private key can be easily calculated. Until
            quite recently, RSA-512, which uses a 512-bit modulus (155 digits), was
            commonly used but can now be easily broken.
          </p>
          <p>
            Many of the numbers that we are factoring are chosen from the
            <a href="http://homes.cerias.purdue.edu/~ssw/cun/index.html">Cunningham
            project</a>. Started in 1925, it is one of the oldest continuously ongoing
            projects in computational number theory. The third edition of the book,
            published by the American Mathematical Society in 2002, is available as a
            <a href="http://www.ams.org/online_bks/conm22/">free download</a>. All results
            obtained since, including those of NFS@Home, are available on the Cunningham
            project website.
          </p>
          <p>
            NFS@Home is hosted at
            <a href="http://www.fullerton.edu">California State University Fullerton</a>,
            and is supported in part by the <a href="http://www.nsf.gov">National Science
            Foundation</a> through <a href="https://access-ci.org">ACCESS</a> resources
            provided by the <a href="http://www.tacc.utexas.edu">Texas Advanced Computing
            Center</a>, the <a href="http://www.sdsc.edu">San Diego Supercomputer
            Center</a>, the <a href="http://www.ncsa.illinois.edu">National Center for
            Supercomputing Applications</a>, and
            <a href="http://www.rcac.purdue.edu/">Purdue University</a> under grant
            number DMS100027.
          </p>
        </section>

        <section class="prose">
          <h2>Getting started</h2>
          <ul>
            <li><a href="info.php"><?php echo tra("Read our rules and policies"); ?></a></li>
            <li>This project uses BOINC. If you are already running BOINC, select
                Attach to Project. If not, <a href="signup.php">sign up</a> or
                <a href="https://boinc.berkeley.edu/download.php">download BOINC</a>.</li>
            <li>When prompted, select <strong><?php echo h(PROJECT); ?></strong> from the
                list of projects.</li>
            <li>If you are running a command-line or pre-5.0 version of BOINC,
                <a href="create_account_form.php">create an account</a> first.</li>
            <li>If you have any problems,
                <a href="https://boinc.berkeley.edu/help.php">get help here</a>.</li>
          </ul>
        </section>

        <section class="quicklinks" aria-labelledby="ql-h">
          <h2 id="ql-h">Everything else</h2>
          <div class="ql-grid">
            <div>
              <h3>Your account</h3>
              <ul>
                <li><a href="home.php">Your account</a></li>
                <li><a href="team.php">Teams</a></li>
                <li><a href="cert1.php">Certificate</a></li>
                <li><a href="apps.php"><?php echo tra("Applications"); ?></a></li>
              </ul>
            </div>
            <div>
              <h3><?php echo tra("Community"); ?></h3>
              <ul>
                <li><a href="forum_index.php"><?php echo tra("Message boards"); ?></a></li>
                <li><a href="profile_menu.php"><?php echo tra("Profiles"); ?></a></li>
                <li><a href="user_search.php">User search</a></li>
                <li><a href="contributions.html">Special contributions</a></li>
              </ul>
            </div>
            <div>
              <h3>Project</h3>
              <ul>
                <li><a href="numbers.php">Status of numbers</a></li>
                <li><a href="stats.php">Statistics</a></li>
                <li><a href="server_status.php">Server status</a></li>
                <li><a href="language_select.php">Languages</a></li>
              </ul>
            </div>
          </div>
        </section>

      </div>

      <div class="col-md-4">

<?php if ($user) { ?>
        <section class="panel panel-primary panel-join" id="join">
          <div class="panel-heading">
            <h2 class="panel-title">Welcome back, <?php echo h($user->name); ?></h2>
          </div>
          <div class="panel-body">
            <p>Your total credit is
               <strong><?php echo number_format($user->total_credit); ?></strong>.</p>
            <a class="btn btn-success" href="home.php">Your account</a>
            <a class="btn btn-default" href="prefs.php?subset=project">Project preferences</a>
            <p style="margin-top:.75rem;font-size:.9375rem">
              <a href="forum_index.php"><?php echo tra("Message boards"); ?></a>
              &middot; <a href="stats.php">Statistics</a>
            </p>
          </div>
        </section>
<?php } else { ?>
        <section class="panel panel-primary panel-join" id="join">
          <div class="panel-heading">
            <h2 class="panel-title">Join <?php echo h(PROJECT); ?></h2>
          </div>
          <div class="panel-body">
            <p>Factor numbers hundreds of digits long with spare time on your own
               machine. Setup takes a few minutes.</p>
            <a class="btn btn-success" href="signup.php">Join <?php echo h(PROJECT); ?></a>
            <a class="btn btn-default" href="https://boinc.berkeley.edu/download.php">Download BOINC first</a>
            <p style="margin-top:.75rem;font-size:.9375rem">
              Already joined? <a href="login_form.php">Log in</a>.
            </p>
          </div>
        </section>
<?php } ?>

        <section class="panel panel-primary">
          <div class="panel-heading">
            <h2 class="panel-title">News</h2>
          </div>
          <div class="panel-body">
<?php
            // Same call the current index.php makes.
            show_news(0, 5);
?>
          </div>
        </section>

<?php
        if (!$stopped) {
            $profile = get_current_uotd();
            if ($profile) {
?>
        <section class="panel panel-primary">
          <div class="panel-heading">
            <h2 class="panel-title"><?php echo tra("User of the day"); ?></h2>
          </div>
          <div class="panel-body">
<?php           show_uotd($profile); ?>
          </div>
        </section>
<?php
            }
        }
?>

      </div>
    </div>
  </div>
</main>

<footer class="site-footer">
  <div class="container-fluid">
    <div class="sponsors">
      <a href="http://www.nsf.gov/"><img src="img/nsf.gif" alt="National Science Foundation"></a>
      <a href="https://access-ci.org/"><img src="img/access-logo-footer.svg" alt="ACCESS" width="300"></a>
      <a href="http://boinc.berkeley.edu/"><img src="img/pb_boinc.gif" alt="Powered by BOINC"></a>
      <a href="http://www.fullerton.edu/"><img src="img/CalStateFullerton-color.png" alt="California State University Fullerton" width="350"></a>
    </div>
    <p class="grant">
      <?php echo h(PROJECT); ?> is hosted at California State University Fullerton and is
      supported in part by the National Science Foundation through
      <a href="https://access-ci.org">ACCESS</a> resources provided by the Texas
      Advanced Computing Center, the San Diego Supercomputer Center, the National
      Center for Supercomputing Applications, and Purdue University under grant
      number DMS100027.
    </p>
  </div>
</footer>

<!-- Bootstrap's collapse and dropdown behaviour, same as page_tail() loads.
     The theme control is handled by nfs2-theme.js in <head>. -->
<script src="sample_jquery.min.js"></script>
<script src="sample_bootstrap.min.js"></script>
</body>
</html>
