#! /bin/sh
# This script will process an index queue
core="episciences"
dir=`dirname $0`
usage="$0 -v(verbose) -d(debug) -t(test) <environnement> <UPDATE|DELETE>";

verbose=0
test=0
debug=0
verbose() {
    [ $verbose -eq 1 ] && echo $*
}
debug() {
    [ $debug -eq 1 ] && echo $*
}
verboseOpt=''
debugOpt=''
testOpt=''
while getopts "tvdh" opt; do
    case $opt in
        v) verbose=1; verboseOpt=-v
           ;;
        d) debug=1;debugOpt=-d
           ;;
        t) test=1;testOpt=-t
           ;;
	h) echo $usage
	   exit 0
	   ;;
    esac
done
shift `expr $OPTIND - 1`
case $# in
    2) : ok
       ;;
    *) echo "Need 2 args"
       echo $usage
       exit 1;
       ;;
esac

case $1 in
    production|preprod|test|development) :oh;;
    *) echo "Arg1 must be an environnement name: production|preprod|test|development"
       exit 1;;
esac


    verbose "-----------------------------------"
    verbose "Do $2 in environnement $1 for $core"
    case $test in
	1)
	    # No & in test case
	    $dir/php_launch_command.sh  $verboseOpt $debugOpt $testOpt $dir/solrJob.php -e $1 --cron $2 -c $core
	    ;;
	0)
	    $dir/php_launch_command.sh  $verboseOpt $debugOpt $testOpt $dir/solrJob.php -e $1 --cron $2 -c $core &
	    ;;
    esac

