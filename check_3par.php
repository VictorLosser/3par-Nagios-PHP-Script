#!/usr/bin/php
<?php
/* 
!! IMPORTANT !!
YOU NEED TO INSTALL THE PHP LIBRARY : PHPSECLIB v1 

THIS SCRIPT IS INSPIRED BY THE PREDECESSOR SCRIPT 
	check_3par.sh
THE MOST FAMOUS NAGIOS SCRIPT FOR 3PAR
THANK YOU ALL CONTRIBUTORS TO THIS SCRIPT !!

# Last update 2010/05/14 fredl@3par.com
# Last update 2011/03/03 ddu@antemeta.fr
# Last update 2015/01/26 by "emil" via https://exchange.nagios.org 3PAR-check-script
# Last update 2015/07/29 pashol
# Last update 2015/09/17 qaxi@seznam.cz
# Last update 2016/12/16 yves.vaccarezza@corsedusud.fr
# Last update 2017/04/12 peterhanraets https://github.com/peterhanraets/nagios-check_3par
# Last update 2017/10/09 gg@grexaut.net https://github.com/GrexAut
# Last update 2017/12/14 qaxi@seznam.cz https://github.com/qaxi/nagios-check_3par
# Last update 2017/12/15 dsbibby https://github.com/dsbibby/nagios-check_3par
# Last update 2018/04/03 pgh2011 https://github.com/pgh2011/check_3par

# Last MAJOR update 2018/07/04 Victor LOSSER	victor.losser@viacesi.fr https://github.com/VictorLosser/3par-Nagios-PHP-Script

I decided to write this script to simplify the code, and to avoid some strangers issues on Nagios that I have not been able to correct.
This script takes some principles of the 3par script in bash.
So we can considered that this is a major update. ;)

Below you can find the help part of the script.

*/

// SPECIFY HERE THE PATH TO YOUR PHPSECLIB FILES
set_include_path(get_include_path() . PATH_SEPARATOR . '/data/neteye/usr/lib64/nagios/plugins/phpseclib');
include('Net/SSH2.php');
include('Crypt/RSA.php');

// Retrieve arguments into variables (they need to be in order)
@$user=$argv[1];
@$host=$argv[2];
@$sshkey=$argv[3]; $sshpubkey .= ".pub";
@$action=$argv[4];
@$warning=intval($argv[5]);
@$critical=intval($argv[6]);

// Output message
$okay = "OK: ";
$warn = "WARNING: ";
$crit = "CRITICAL: ";
$unkn = "UNKNOWN: ";

// Nagios exit codes
$e_okay = 0;
$e_warn = 1;
$e_crit = 2;
$e_unkn = 3;

// HELP DISPLAY
if (!$action || !$host || !$user || !$sshkey) {
		print "
Usage:

check_3par.php <username> <hostname> <public key path> <check_xxx> <warn value*> <critical value*>
	*optional depending the mode chosen

   check_pd :   Check status of physical disks
                   Degraded ->      Warning
                   Failed ->        Critical

   check_node :    Check status of controller nodes
                   Degraded ->      Warning
                   Failed ->        Critical

   check_vv :   Check status of virtual volumes
                   Degraded ->      Warning
                   Failed ->        Critical

   check_ld :   Check status of logical disks
                   Degraded ->      Warning
                   Failed ->        Critical

   check_port_fc : Check status of FC ports
                   loss_sync ->     Warning
                   config_wait ->   Warning
                   login_wait ->    Warning
                   non_participate ->   Warning
                   error ->         Critical

   check_cap_ssd : Check used SSD capacity
                   >= threshold entered in args ->         Warning
                   >= threshold entered in args ->         Critical

   check_ps_node : Check Power Supply Node
                   Degraded ->      Warning
                   Failed ->        Critical

   check_ps_cage : Check Power Supply Cage
                   Degraded ->      Warning
                   Failed ->        Critical

   check_health :  Check overall state of the system

   check_alerts : Check status of system alerts

";
		exit($e_unkn);
}

// SSH CONNECTION INITIALIZATION
$connection = new Net_SSH2($host,22,100);
$key = new Crypt_RSA();
$key->loadKey(file_get_contents($sshkey));
if (!$connection->login($user, $key)) {
	print "SSH Connection Failed\n";
	exit($e_unkn);
}

// STARTING THIS POINT, WE CHECK WHICH COMMAND IS ENTERED AND WE EXECUTE THE CORRESPONDING ACTION

if ($action == "check_cap_ssd") {
	$output = $connection->exec("showpd -p -devtype SSD -showcols CagePos,Size_MB,Free_MB -csvtable");
	$output = shell_exec("echo \"$output\" | grep total");
	$output = trim($output);

	$lastline = shell_exec("echo \"$output\" | tail -1");
	if ($lastline == "No PDs listed" || $lastline == "0,0") {
		print "ERROR : 3PAR said that there are no PDs listed or that total used capacity is 0,0\n"; exit($e_unkn);
	}

	$totcap = shell_exec("echo \"$output\" | tail -1 | cut -d, -f2");
	$freecap = shell_exec("echo \"$output\" | tail -1 | cut -d, -f3");
	$usedcappc = round(100 - (($freecap * 100) / $totcap), 1);
	$usedcap = $totcap - $freecap;
	$warncapraw = $totcap * $warning / 100;
	$critcapraw = $totcap * $critical / 100;

	$DIVISEUR = 0.001048576;

        $usedcapgb = round($usedcap * $DIVISEUR, 1);
        $freecapgb = round($freecap * $DIVISEUR, 1);
        $totcapgb = round($totcap * $DIVISEUR, 1);

	$perfmsg = "|UsedSpace(%)=".$usedcappc."%;$warning;$critical";
	$perfmsg .= " UsedSpace(MiB)=".$usedcap."MiB;$warncapraw;$critcapraw;0;$totcap";

	$textmsg = "Used SSD raw capacity $usedcapgb GB ($usedcappc %) $perfmsg";

	if ($usedcappc > $critical) {
		print $crit.$textmsg; exit($e_crit);
	}
	elseif ($usedcappc > $warning) {
		print $warn.$textmsg; exit($e_warn);
	}
	else {
		print $okay.$textmsg."\nFree SSD capacity $freecapgb GB"."\nTotal SSD capacity $totcapgb GB\n"; exit($e_okay);
	}
}

if ($action == "check_ps_cage") {
        $output = $connection->exec("showcage -d");
	$bad_output = shell_exec("echo \"$output\" | grep -c -i -e failed -e degraded");

        if (check("failed")) {
                print $crit."There are failed cages. Click to view details...\n\n$bad_output"; exit($e_crit);
        }
        elseif (check("degraded")) {
                print $warn."There are degraded cages. Click to view details...\n\n$bad_output"; exit($e_warn);
        }
        else {
                print $okay."All cages power supply have normal status. No stress.\n"; exit($e_okay);
        }
}

if ($action == "check_pd") {
        $output = $connection->exec("showpd");

        if (check("failed")) {
                print $crit."There are failed physical disks. Click to view details...\n\n$output"; exit($e_crit);
        }
        elseif (check("degraded")) {
                print $warn."There are degraded physical disks. Click to view details...\n\n$output"; exit($e_warn);
        }
        else {
                print $okay."All physical disks have normal status. No stress.\n"; exit($e_okay);
        }
}

if ($action == "check_node") {
        $output = $connection->exec("shownode -state");

        if (check("failed")) {
                print $crit."There are failed nodes. Click to view details...\n\n$output"; exit($e_crit);
        }
        elseif (check("degraded")) {
                print $warn."There are degraded nodes. Click to view details...\n\n$output"; exit($e_warn);
        }
        else {
                print $okay."All nodes have normal status. No stress.\n"; exit($e_okay);
        }
}

if ($action == "check_ps_node") {
        $output = $connection->exec("shownode -ps");

        if (check("failed")) {
                print $crit."There are failed nodes. Click to view details...\n\n$output"; exit($e_crit);
        }
        elseif (check("degraded")) {
                print $warn."There are degraded nodes. Click to view details...\n\n$output"; exit($e_warn);
        }
        else {
                print $okay."All nodes have normal status. No stress.\n"; exit($e_okay);
        }
}

if ($action == "check_vv") {
	$output = $connection->exec("showvv -showcols Name,State -notree -nohdtot");
	$normal_list = shell_exec("echo \"$output\" | cut -f 1 -d ' ' ");
	$degraded_list = shell_exec("echo \"$output\" | grep degraded | cut -f 1 -d ' ' ");
	$failed_list = shell_exec("echo \"$output\" | grep failed | cut -f 1 -d ' ' ");

        if (check("failed")) {
		$total_failed = total("failed");
                print $crit."There are $total_failed failed VVs. Click to view them...\n\n$failed_list"; exit($e_crit);
        }
        elseif (check("degraded")) {
		$total_degraded = total("degraded");
                print $warn."There are $total_degraded degraded VVs. Click to view them...\n\n$degraded_list"; exit($e_warn);
        }
        else {
		$total_normal = total("normal");
                print $okay."All $total_normal VVs have normal status. Click to view them...\n\n$normal_list"; exit($e_okay);
        }
}

if ($action == "check_ld") {
	$output = $connection->exec("showld -state");

	if (check("failed")) {
                print $crit."There are failed LDs. Contact 3PAR support."; exit($e_crit);
        }
        elseif (check("degraded")) {
                print $warn."There are degraded LDs. Contact 3PAR support."; exit($e_warn);
        }
        else {
		$total_normal = total("normal");
                print $okay."All $total_normal LDs have normal status. No stress.\n"; exit($e_okay);
        }
}

if ($action == "check_port_fc") {
	$output = $connection->exec("showport -nohdtot");	
	
	$output = shell_exec("echo \"$output\" | grep -v -i iscsi | grep -v -i rcip | grep -v -i free");

        if (check("error")) {
                print $crit."Some ports are in the error state. Click to view details...\n\n$output"; exit($e_crit);
        }
        elseif (check("loss_sync") || check("config_wait") || check("login_wait") || check("non_participate")) {
                print $warn."Some ports are in an abnormal state (loss_sync, config_wait, login_wait or non_participate). Click to view alerts detail...\n\n$output"; exit($e_warn);
        }
        else {
                print $okay."All FC ports have normal status (ready or offline). No stress.\n"; exit($e_okay);
        }
}

if ($action == "check_alerts") {
	$output = $connection->exec("showalert");

	if (check("major") || check("fatal") || check("critical")) {
		print $crit."The system has fatal/critical/major level alerts. Click to view alerts detail...\n\n$output"; exit($e_crit);
	}
	elseif (check("minor") || check("degraded")) {
		print $warn."The system has minor/degraded level alerts. Click to view alerts detail...\n\n$output"; exit($e_warn);
	}
	else {
		print $okay."No alarms. No stress.\n"; exit($e_okay);
	}
}

if ($action == "check_health") {
        $output = $connection->exec("checkhealth -quiet");
	
	$total = shell_exec("echo \"$output\" | grep total | awk '{print $1}' ");
	$total = trim($total);

        if ($total > 0) {
                print $warn."The system has $total problems. Click to view alerts detail...\n\n".$output; exit($e_warn);
        }
        else {
                print $okay."The system is healthy. No stress.\n"; exit($e_okay);
        }
}

// FUNCTIONS TO FACTORIZE SOME PROCESSES

// Check if the number of lines of $level state is greater than 0
// Return true or false
function check($level){
	$out = $GLOBALS['output'];
	$number = shell_exec("echo \"$out\" | grep -c -i $level");
	if ($number >0) {
		return true;
	}
	else {
		return false;
	}
}

// Return the number of lines of $level state
function total($level) {
	$out = $GLOBALS['output'];
	$total = shell_exec("echo \"$out\" | grep -c $level");
	$total = trim($total);
	return $total;
}

?>

