#!/usr/bin/env perl

use LWP::Simple;
use Socket;

use strict;
use warnings;

# change these values
#my $login    = 'username';
my $login    = 'admin';
#my $password = 'password';
my $password = 'admin';
my $domain   = 'mydynamicdns.example.com';

my $verbose = 1;

#my $poweradmin_url = 'http://example.com/poweradmin/';
my $poweradmin_url = 'http://127.0.0.1/poweradmin/';

# try to get client ip address using whatismyip service
#my $ipaddress =
#  LWP::Simple::get("http://automation.whatismyip.com/n09230945.asp")
#  or die("Error: Could not get your global IP address!\n");
my $ipaddress = '127.0.0.1';
if ( $ipaddress =~ /([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})/ ) {
    $ipaddress = $1;
}
else {
    print "Error: Could not get your global IP address!\n";
    exit;
}

print "Updating the IP address ($ipaddress) now ... \n";

# TODO: how it will work with https?
# insert authentication data to url
$poweradmin_url =~ s/^http:\/\//http:\/\/$login:$password\@/;
my $response =
  LWP::Simple::get( "$poweradmin_url/dynamic_update.php"
      . "?hostname=$domain&myip=$ipaddress&verbose=1" )
  or die($!);

if ( !defined $response || $response eq "" ) {
    print "Error: Could not contact your poweradmin web server\n";
    exit(0);
}

print "Status: $response\n";
