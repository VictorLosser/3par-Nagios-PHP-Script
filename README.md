# Nagios check_3par

Basic support for monitoring HP 3PAR arrays by Nagios

## Usage
```
Usage:
check_3par.php <username> <hostname> <public key path> <check_xxx> <warn value * > <critical value * >
	*optional depending the mode chosen
```

## Supported commands 
```

   check_pd :   Check status of physical disks
                   Degraded ->      Warning
                   Failed ->        Critical

   check_node :    Check status of controller nodes
                   Degraded ->      Warning
                   Failed ->        Critical

   check_ld :   Check status of logical disks
                   Degraded ->      Warning
                   Failed ->        Critical

   check_vv :   Check status of virtual volumes
                   Degraded ->      Warning
                   Failed ->        Critical

   check_port_fc : Check status of FC ports
                   loss_sync ->     Warning
                   config_wait ->   Warning
                   login_wait ->    Warning
                   non_participate ->   Warning
                   error ->         Critical

   check_cap_ssd : Check used SSD capacity
                   >= 80 ->         Warning
                   >= 90 ->         Critical

   check_ps_node : Check Power Supply Node
                   Degraded ->      Warning
                   Failed ->        Critical

   check_ps_cage : Check Power Supply Cage
                   Degraded ->      Warning
                   Failed ->        Critical
				   
   check_health :  Check overall state of the system
   
   check_alerts : Check status of system alerts
				   
```

## Usage in Nagios

Install PHPSECLIB library http://phpseclib.sourceforge.net/
Copy all the files to the directory you want, and specify the path in the script.
Copy file `check_3par` to Nagios plugins directory (for example `/usr/lib/nagios/plugins/`).

## Links

Nagios plugin developement [https://nagios-plugins.org/doc/guidelines.html#PLUGOPTIONS]
