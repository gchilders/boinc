/* Platform independent version definitions... */

#ifndef BOINC_VERSION_H
#define BOINC_VERSION_H

/* Major part of BOINC version number */
#define BOINC_MAJOR_VERSION 7

/* Minor part of BOINC version number */
#define BOINC_MINOR_VERSION 25

/* Release part of BOINC version number */
#define BOINC_RELEASE 0

// Release part of wrapper version number; must match
// samples/wrapper/Makefile
// win_build/wrapper.vcxproj
// configure.ac
// mac?
#define WRAPPER_RELEASE 26018

// Release part of vboxwrapper version number; must match
// samples/vboxwrapper/Makefile
// win_build/vboxwrapper.vcxproj
// configure.ac
// mac?
#define VBOXWRAPPER_RELEASE 26207

// Release part of worker version number; must match
// samples/worker/Makefile
// win_build/worker.vcxproj
// configure.ac
// mac?
#define WORKER_RELEASE 2

/* String representation of BOINC version number */
#define BOINC_VERSION_STRING "7.25.0"

/* Package is a pre-release (Alpha/Beta) package */
#define BOINC_PRERELEASE 1

#if (defined(_WIN32) || defined(__APPLE__))
/* Name of package */
#define PACKAGE "boinc"

/* Define to the address where bug reports for this package should be sent. */
#define PACKAGE_BUGREPORT ""

/* Define to the full name of this package. */
#define PACKAGE_NAME "BOINC"

/* Define to the full name and version of this package. */
#define PACKAGE_STRING "BOINC 7.25.0"

/* Define to the one symbol short name of this package. */
#define PACKAGE_TARNAME "boinc"

/* Define to the version of this package. */
#define PACKAGE_VERSION "7.25.0"

#endif /* #if (defined(_WIN32) || defined(__APPLE__)) */

#endif /* #ifndef BOINC_VERSION_H */

