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

// cancel_jobs_by_name NAME
//
// cancel jobs from min-ID to max-ID inclusive

#include <stdio.h>

#include "backend_lib.h"
#include "sched_config.h"
#include "sched_util.h"

void usage() {
    fprintf(stderr, "Usage: cancel_jobs_by_name NAME\n");
    exit(1);
}

int main(int argc, char** argv) {
    DB_WORKUNIT wu;
    char name[512], buf[512];
    int count=0;

    if (argc != 2) usage();
    sprintf(name,"%s", argv[1]);
    if (!name[0]) usage();

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
           printf("Canceling %d\n",wu.id);
           retval = cancel_jobs(wu.id, wu.id);; 
           if (retval) {
               fprintf(stderr, "cancel_jobs() failed: %s\n", boincerror(retval));
               exit(retval);
           }
      }
    printf("canceled %d workunits\n",count);
    boinc_db.close();
    return 0;

}

