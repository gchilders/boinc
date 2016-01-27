#!/bin/sh

# This file is part of BOINC.
# http://boinc.berkeley.edu
# Copyright (C) 2015 University of California
#
# BOINC is free software; you can redistribute it and/or modify it
# under the terms of the GNU Lesser General Public License
# as published by the Free Software Foundation,
# either version 3 of the License, or (at your option) any later version.
#
# BOINC is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
# See the GNU Lesser General Public License for more details.
#
# You should have received a copy of the GNU Lesser General Public License
# along with BOINC.  If not, see <http://www.gnu.org/licenses/>.
#
#
# Master script to build Universal Binary libraries needed by BOINC:
# curl-7.26.0 with c-ares-1.9.1, openssl-1.0.1e, wxWidgets-3.0.0,
# sqlite3.7.14.1, FreeType-2.4.10 and FTGL-2.1.3
#
# by Charlie Fenton 7/21/06
# Updated 10/18/11 for OS 10.7 lion and XCode 4.2
# Updated 7/6/11 for wxMac-2.8.10 and Unicode
# Updated 6/25/12 for curl-7.26.0 and c-ares-1.9.1
# Updated 6/26/12 for openssl-1.0.1c
# Updated 8/3/12 for FreeType-2.4.10 and FTGL-2.1.3~rc5
# Updated 12/11/12 for sqlite3.7.14.1 from sqlite-autoconf-3071401
# Updated 11/30/13 for openssl-1.0.1e
# Updated 2/7/14 for wxWidgets-3.0.0
# Updated 2/11/14 for c-ares 1.10.0, curl 7.35.0, openssl 1.0.1f, sqlite 3.8.3
# Updated 9/2/14 for openssl 1.0.1h
# Updated 4/8/15 for curl 7.39.0, openssl 1.0.1j
# Updated 11/30/15 to allow putting third party packages in ../mac3rdParty/
# Updated 11/30/15 to return error code indicating which builds failed
# Updated 1/6/16 for curl 7.46.0, openssl 1.0.2e, sqlite 3.9.2, FreeType-2.6.2
#
# Download these seven packages and place them in a common parent directory
# with the BOINC source tree. For compatibility with Travis CI builds, they
# can instead be placed in the directory ../mac3rdParty/
#
# When the packages are placed in the parent directory, this script creates
# symbolic links to them in ../mac3rdParty/.
#
## In Terminal, cd to the mac_build directory of the boinc tree; for 
## example:
##     cd [path]/boinc/mac_build/
## then run this script:
##     source setupForBoinc.sh [ -clean ]
#
# the -clean argument will force a full rebuild of everything.
#
# This script will work even if you have renamed the boinc/ directory
#

function make_symlink_if_needed() {
    cd ../mac3rdParty/
    if [ ! -d "${1}" ]; then
        if [ -d "../../${1}" ]; then
            ln -s "../../${1}"
        fi
    fi

    cd "${SCRIPT_DIR}"
}

if [ "$1" = "-clean" ]; then
  cleanit="-clean"
else
  cleanit=""
fi

caresOK="NO"
curlOK="NO"
opensslOK="NO"
wxWidgetsOK="NO"
sqlite3OK="NO"
freetypeOK="NO"
ftglOK="NO"
finalResult=0

SCRIPT_DIR=`pwd`

if [ ! -d ../mac3rdParty ]; then
    mkdir ../mac3rdParty
fi

echo ""
echo "----------------------------------"
echo "------- BUILD C-ARES-1.10.0 ------"
echo "----------------------------------"
echo ""

make_symlink_if_needed c-ares-1.10.0

cd ../mac3rdParty/c-ares-1.10.0/
if [  $? -eq 0 ]; then
    source "${SCRIPT_DIR}/buildc-ares.sh" ${cleanit}
    if [  $? -eq 0 ]; then
        caresOK="YES"
    fi
fi

cd "${SCRIPT_DIR}"

echo ""
echo "----------------------------------"
echo "------- BUILD CURL-7.46.0 --------"
echo "----------------------------------"
echo ""

make_symlink_if_needed curl-7.46.0

cd ../mac3rdParty/curl-7.46.0/
if [  $? -eq 0 ]; then
    source "${SCRIPT_DIR}/buildcurl.sh" ${cleanit}
    if [  $? -eq 0 ]; then
        curlOK="YES"
    fi
fi

cd "${SCRIPT_DIR}"

echo ""
echo "----------------------------------"
echo "----- BUILD OPENSSL-1.0.2e -------"
echo "----------------------------------"
echo ""

make_symlink_if_needed openssl-1.0.2e

cd ../mac3rdParty/openssl-1.0.2e/
if [  $? -eq 0 ]; then
    source "${SCRIPT_DIR}/buildopenssl.sh" ${cleanit}
    if [  $? -eq 0 ]; then
        opensslOK="YES"
    fi
fi

cd "${SCRIPT_DIR}"

echo ""
echo "----------------------------------"
echo "----- BUILD wxWidgets-3.0.0 ------"
echo "----------------------------------"
echo ""

make_symlink_if_needed wxWidgets-3.0.0

cd ../mac3rdParty/wxWidgets-3.0.0/
if [  $? -eq 0 ]; then
    source "${SCRIPT_DIR}/buildWxMac.sh" ${cleanit}
    if [  $? -eq 0 ]; then
        wxWidgetsOK="YES"
    fi
fi

cd "${SCRIPT_DIR}"

echo ""
echo "----------------------------------"
echo "------- BUILD sqlite-3.9.2 -------"
echo "----------------------------------"
echo ""

make_symlink_if_needed sqlite-autoconf-3090200

cd ../mac3rdParty/sqlite-autoconf-3090200/
if [  $? -eq 0 ]; then
    source "${SCRIPT_DIR}/buildsqlite3.sh" ${cleanit}
    if [  $? -eq 0 ]; then
        sqlite3OK="YES"
    fi
fi

cd "${SCRIPT_DIR}"

echo ""
echo "----------------------------------"
echo "----- BUILD FreeType-2.6.2 ------"
echo "----------------------------------"
echo ""

make_symlink_if_needed freetype-2.6.2

cd ../mac3rdParty/freetype-2.6.2/
if [  $? -eq 0 ]; then
    source "${SCRIPT_DIR}/buildfreetype.sh" ${cleanit}
    if [  $? -eq 0 ]; then
        freetypeOK="YES"
    fi
fi

cd "${SCRIPT_DIR}"

echo ""
echo "----------------------------------"
echo "------ BUILD FTGL-2.1.3~rc5 ------"
echo "----------------------------------"
echo ""

make_symlink_if_needed ftgl-2.1.3~rc5

cd ../mac3rdParty/ftgl-2.1.3~rc5/
if [  $? -eq 0 ]; then
    source "${SCRIPT_DIR}/buildFTGL.sh" ${cleanit}
    if [  $? -eq 0 ]; then
        ftglOK="YES"
    fi
fi

cd "${SCRIPT_DIR}"

if [ "${caresOK}" = "NO" ]; then
    echo ""
    echo "-----------------------------------"
    echo "------------ WARNING --------------"
    echo "------------         --------------"
    echo "-- COULD NOT BUILD C-ARES-1.10.0 --"
    echo "-----------------------------------"
    echo ""

    finalResult=$[ finalResult | 1 ]
fi

if [ "${curlOK}" = "NO" ]; then
    echo ""
    echo "-----------------------------------"
    echo "------------ WARNING --------------"
    echo "------------         --------------"
    echo "--- COULD NOT BUILD CURL-7.46.0 ---"
    echo "-----------------------------------"
    echo ""

    finalResult=$[ finalResult | 2 ]
fi

if [ "${opensslOK}" = "NO" ]; then
    echo ""
    echo "----------------------------------"
    echo "------------ WARNING -------------"
    echo "------------         -------------"
    echo "- COULD NOT BUILD OPENSSL-1.0.2e -"
    echo "----------------------------------"
    echo ""
    
    finalResult=$[ finalResult | 4 ]
fi

if [ "${wxWidgetsOK}" = "NO" ]; then
    echo ""
    echo "-----------------------------------"
    echo "------------ WARNING --------------"
    echo "------------         --------------"
    echo "- COULD NOT BUILD wxWidgets-3.0.0 -"
    echo "-----------------------------------"
    echo ""
    
    finalResult=$[ finalResult | 8 ]
fi

if [ "${sqlite3OK}" = "NO" ]; then
    echo ""
    echo "----------------------------------"
    echo "------------ WARNING -------------"
    echo "------------         -------------"
    echo "-- COULD NOT BUILD sqlite-3.9.2 --"
    echo "----------------------------------"
    echo ""
    
    finalResult=$[ finalResult | 16 ]
fi

if [ "${freetypeOK}" = "NO" ]; then
    echo ""
    echo "-----------------------------------"
    echo "------------ WARNING --------------"
    echo "------------         --------------"
    echo "- COULD NOT BUILD FreeType-2.6.2 -"
    echo "-----------------------------------"
    echo ""
    
    finalResult=$[ finalResult | 32 ]
fi

if [ "${ftglOK}" = "NO" ]; then
    echo ""
    echo "-----------------------------------"
    echo "------------ WARNING --------------"
    echo "------------         --------------"
    echo "- COULD NOT BUILD FTGL-2.1.3~rc5 --"
    echo "-----------------------------------"
    echo ""
    
    finalResult=$[ finalResult | 64 ]
fi

echo ""

return $finalResult
