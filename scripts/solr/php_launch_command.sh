#!/bin/bash

max=60
verbose=0
test=0
debug=0
PHP_BIN="/usr/bin/php"

verbose() {
    [ $verbose -eq 1 ] && echo $*
}
debug() {
    [ $debug -eq 1 ] && echo $*
}

while getopts "m:tvdp:" opt; do
    case $opt in
        m) max="$OPTARG"
           ;;
        v) verbose=1
           ;;
        d) debug=1
           ;;
        t) test=1
           ;;
	p) phpCommandList="$OPTARG $phpCommandList"
    esac
done
shift `expr $OPTIND - 1`
command=$1

nbprocess=$(ps ax | grep "$command" | wc -l)
verbose "Nbr de process pour $command: $nbprocess"

if [[ $nbprocess -gt $max ]] ; then
    debug "Nb of process $command: $nbprocess > $max"
  echo "Too many process"
else
    verbose "exec $PHP_BIN $*";
    if [ $test -eq 1 ]; then
	echo "exec $PHP_BIN $*";
    else 
	exec $PHP_BIN $*
    fi
fi
