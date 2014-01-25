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

// restrict_jobs NAME userid
//
// cancel jobs from min-ID to max-ID inclusive

#include <stdio.h>

#include "backend_lib.h"
#include "sched_config.h"
#include "sched_util.h"

void usage() {
    fprintf(stderr, "Usage: restrict_jobs NAME userid\n");
    exit(1);
}

int main(int argc, char** argv) {
    DB_WORKUNIT wu;
    char name[512], buf[512];
    int userid, count=0;

    if (argc != 3) usage();
    sprintf(name,"%s", argv[1]);
    userid = atoi(argv[2]);
    if (!name[0] || !userid) usage();

    int retval = config.parse_file();
    if (retval) { 
        fprintf(stderr,"can't read config file\n"); 
        exit(1); 
    }

    retval = boinc_db.open(
        config.db_name, config.db_host, config.db_user, config.db_passwd
    );
    if (retval) {
        printf("boinc_db.open: %s\n", boincerror(retval));
        exit(1);
    }
    sprintf(buf,"WHERE name LIKE '%s_%%' and assimilate_state != 2",name);
    
     while (!wu.enumerate(buf)) {
           count++;
           printf("Workunit id %d to userid %d\n",wu.id,userid);
           retval = restrict_wu_to_user(wu, userid); 
           if (retval) {
               fprintf(stderr, "restrict_wu_to_user() failed: %s\n", boincerror(retval));
               exit(retval);
           }
      }
    printf("restricted %d workunits to user %d\n",count,userid);
    boinc_db.close();
    return 0;

}

