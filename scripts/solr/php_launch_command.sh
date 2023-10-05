#!/bin/bash

max=60
verbose=0
test=0
debug=0

if test -f "/usr/bin/php7.4"; then
    PHP_BIN="/usr/bin/php7.4"
else
  PHP_BIN="/usr/bin/php"
fi



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
	        ;;
	      *)
	        ;;
    esac
done
shift $(expr $OPTIND - 1)
command=$1

nbprocess=$(ps ax | grep "$command" | wc -l)
verbose "Number of processes for $command: $nbprocess"

if [ $nbprocess -gt $max ]; then
    debug "Number of processes for $command: $nbprocess exceeds maximum allowed ($max)"
    echo "Too many processes"
    exit 1
else
    verbose "Executing $PHP_BIN $*"
    if [ $test -eq 1 ]; then
        echo "Executing $PHP_BIN $*"
    else
        exec $PHP_BIN $*
    fi
fi

