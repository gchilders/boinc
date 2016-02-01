#!/bin/bash

WIDTH=800
HEIGHT=320
DEST=/home/boincadm/projects/nfs/html/user/rrdtool
SRC=/home/boincadm/projects/nfs/html/cron

function make_set {
#$1 = namebase
#$2 = dest path base
#$3 = title
#$4 = vertical label
#$5 = DEF
        echo $1: daily
        rrdtool graph \
                $2/$1_daily.png \
                -t "$3" \
                -v "$4" \
                -w $WIDTH -h $HEIGHT \
                --end now \
                --start now-1d \
                --step 300s \
                DEF:avg=$5 \
                LINE:avg#0000FF

        #weekly
        echo $1: weekly
        rrdtool graph \
                $2/$1_weekly.png \
                -t "$3" \
                -v "$4" \
                -w $WIDTH -h $HEIGHT \
                --end now \
                --start now-7d \
                --step 300s \
                DEF:avg=$5 \
                LINE:avg#0000FF

        #monthly
        echo $1: monthly
        rrdtool graph \
                $2/$1_monthly.png \
                -t "$3" \
                -v "$4" \
                -w $WIDTH -h $HEIGHT \
                --end now \
                --start now-30d \
                DEF:avg=$5 \
                LINE:avg#0000FF

#pas indispensable pour le moment
        #yearly
        echo $1: yearly
        rrdtool graph \
                $2/$1_yearly.png \
                -t "$3" \
                -v "$4" \
                -w $WIDTH -h $HEIGHT \
                --end now \
                --start now-365d \
                DEF:avg=$5 \
                LINE:avg#0000FF

}

#================================================================================================================

make_set "ready" $DEST "Results ready to send" "Results" $SRC/results.rrd:ready:AVERAGE
make_set "pending" $DEST "Results in progress" "Results" $SRC/results.rrd:inprogress:AVERAGE
make_set "disk" $DEST "Disk usage" "Percent" $SRC/disk.rrd:diskused:LAST
#make_set "recv" $DEST "Received results" "RPS" $SRC/recv.rrd:resultrecv:AVERAGE

#================================================================================================================

