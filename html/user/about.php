<?php
// About page for NFS@Home.
//
// Install as html/user/about.php. The navbar that sample_navbar() emits links
// here from every page, so this fills that gap.
//
// Deliberately built on page_head()/page_tail() rather than its own HTML, so
// it inherits the navbar, the footer, the stylesheet and the theme control
// with no duplication. The prose is the same text as the front page, split
// into sections and expanded with links to the pages that go with each part.
//
// page_head() prints the page title as <h2>, so the section headings here are
// <h3>. Do not "promote" them to <h2>: that would put them at the same level
// as the page title and break the heading outline.

require_once("../inc/util.inc");

// Same string sample_navbar() uses for the link that points here, so the
// page title and the menu item cannot drift apart.
page_head(tra("About %1", PROJECT));
?>

<div class="prose">

<p class="lead">
NFS@Home is a research project that uses Internet-connected computers to do
the lattice sieving step in the Number Field Sieve factorization of large
integers.
</p>

<h3>What the project does</h3>

<p>
As a young school student, you gained your first experience at breaking an
integer into prime factors, such as 15 = 3 * 5 or 35 = 5 * 7. NFS@Home is a
continuation of that experience, only with integers that are hundreds of
digits long. Most recent large factorizations have been done primarily by
large clusters at universities or AI companies. With NFS@Home you can participate in
state-of-the-art factorizations simply by downloading and running a free
program on your computer.
</p>

<p>
Factoring a number this large is done in stages. Sieving is the stage that
parallelises well across many independent computers, which is what makes it
suited to volunteer computing: each workunit covers its own slice of the
search space and needs no contact with the others. The later stages, linear
algebra and the square root, are run on dedicated hardware afterwards.
</p>

<p>
You can see what is being sieved at the moment, and how far along each number
is, on the <a href="index.php">front page</a> or in more detail on the
per-application status pages:
</p>

<ul>
    <li><a href="crunching.php">lasieved</a></li>
    <li><a href="crunching_es.php">lasievee_small</a></li>
    <li><a href="crunching_e.php">lasievee</a></li>
    <li><a href="crunching_fs.php">lasievef_small</a></li>
    <li><a href="crunching_myf.php">lasievef</a></li>
    <li><a href="crunching_cs.php">cudasieve</a></li>
</ul>

<p>
The applications differ in the sieving parameters they use, and therefore in
how much memory and time a workunit needs. <a href="apps.php">Applications</a>
lists the versions and platforms currently available, and you can choose which
ones your computer takes on in your project preferences. cudasieve is the
newest of them, a GPU implementation still under test.
</p>

<h3>Why integer factorization is interesting</h3>

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

<h3>The Cunningham project</h3>

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
Completed factorizations are listed on
<a href="numbers.php">Status of numbers</a>, together with the size of the
factors found and a link to the full result for each one. Notable results are
announced on the <a href="forum_index.php">message boards</a>.
</p>

<h3>Taking part</h3>

<p>
The project runs on <a href="https://boinc.berkeley.edu/">BOINC</a>, which
runs the work in the background and only when you are not using the machine
yourself. To join:
</p>

<ul>
    <li><a href="info.php">Read the rules and policies</a> first.</li>
    <li>If you are already running BOINC, choose Add Project and select
        <?php echo PROJECT; ?> from the list.</li>
    <li>If not, <a href="signup.php">sign up here</a>, or
        <a href="https://boinc.berkeley.edu/download.php">download BOINC</a>
        and then attach to this project.</li>
    <li>If anything goes wrong,
        <a href="https://boinc.berkeley.edu/help.php">help is available</a>,
        and the <a href="forum_index.php">message boards</a> are the best place
        to ask about this project specifically.</li>
</ul>

<p>
There is no minimum commitment, and you can stop at any time. Credit for
completed work is tracked per application, and
<a href="stats.php">statistics</a> are published for individuals and
<a href="team.php">teams</a>.
</p>

<h3>Who runs it</h3>

<p>
NFS@Home is hosted at
<a href="http://www.fullerton.edu">California State University Fullerton</a>,
and is supported in part by the <a href="http://www.nsf.gov">National Science
Foundation</a> through <a href="https://access-ci.org">ACCESS</a> resources
provided by the <a href="http://www.tacc.utexas.edu">Texas Advanced Computing
Center</a>, the <a href="http://www.sdsc.edu">San Diego Supercomputer
Center</a>, the <a href="http://www.ncsa.illinois.edu">National Center for
Supercomputing Applications</a>, and
<a href="http://www.rcac.purdue.edu/">Purdue University</a> under grant number
DMS100027.
</p>

<p>
The post-processing that turns sieving results into factors is carried out on
those resources. Volunteers who have made particular contributions to the
project are acknowledged on the
<a href="contributions.html">special contributions</a> page.
</p>

<h3>Elsewhere</h3>

<ul>
    <li><a href="server_status.php">Server status</a> and
        <a href="numbers.php">status of numbers</a></li>
    <li><a href="forum_index.php">Message boards</a></li>
    <li><a href="stats.php">Statistics</a> and
        <a href="apps.php">applications</a></li>
    <li><a href="http://homes.cerias.purdue.edu/~ssw/cun/index.html">The
        Cunningham project</a></li>
    <li><a href="https://boinc.berkeley.edu/">BOINC</a></li>
</ul>

</div>

<?php
page_tail();
?>
