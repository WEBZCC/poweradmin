<?php

/*  Poweradmin, a friendly web-based admin tool for PowerDNS.
 *  See <https://www.poweradmin.org> for more details.
 *
 *  Copyright 2007-2010  Rejo Zenger <rejo@zenger.nl>
 *  Copyright 2010-2022  Poweradmin Development Team
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * Script that handles request to add new records to existing zone
 *
 * @package     Poweradmin
 * @copyright   2007-2010 Rejo Zenger <rejo@zenger.nl>
 * @copyright   2010-2022  Poweradmin Development Team
 * @license     https://opensource.org/licenses/GPL-3.0 GPL
 */

use Poweradmin\AppFactory;
use Poweradmin\DnsRecord;
use Poweradmin\Dnssec;
use Poweradmin\RecordType;
use Poweradmin\Syslog;
use Poweradmin\Validation;

require_once 'inc/toolkit.inc.php';
require_once 'inc/message.inc.php';

include_once 'inc/header.inc.php';

$app = AppFactory::create();
$pdnssec_use = $app->config('pdnssec_use');
$iface_add_reverse_record = $app->config('iface_add_reverse_record');

if (do_hook('verify_permission', 'zone_content_view_others')) {
    $perm_view = "all";
} elseif (do_hook('verify_permission', 'zone_content_view_own')) {
    $perm_view = 'own';
} else {
    $perm_view = 'none';
}

if (do_hook('verify_permission', 'zone_content_edit_others')) {
    $perm_content_edit = 'all';
} elseif (do_hook('verify_permission', 'zone_content_edit_own')) {
    $perm_content_edit = 'own';
} elseif (do_hook('verify_permission', 'zone_content_edit_own_as_client')) {
    $perm_content_edit = 'own_as_client';
} else {
    $perm_content_edit = 'none';
}

if (do_hook('verify_permission', 'zone_meta_edit_others')) {
    $perm_meta_edit = "all";
} elseif (do_hook('verify_permission', 'zone_meta_edit_own')) {
    $perm_meta_edit = "own";
} else {
    $perm_meta_edit = "none";
}

if (!isset($_GET['id']) || !Validation::is_number($_GET['id'])) {
    error(ERR_INV_INPUT);
    include_once('inc/footer.inc.php');
    exit;
}
$zone_id = htmlspecialchars($_GET['id']);

$ttl = $app->config('dns_ttl');
if (isset($_POST['ttl']) && Validation::is_number($_POST['ttl'])) {
    $ttl = $_POST['ttl'];
}

$prio = 10;
if (isset($_POST['prio']) && Validation::is_number($_POST['prio'])) {
    $prio = $_POST['prio'];
}

$name = $_POST['name'] ?? "";
$type = $_POST['type'] ?? "";
$content = $_POST['content'] ?? "";

$user_is_zone_owner = do_hook('verify_user_is_owner_zoneid', $zone_id);
$zone_type = DnsRecord::get_domain_type($zone_id);
$zone_name = DnsRecord::get_domain_name_by_id($zone_id);

if (isset($_POST["commit"])) {
    if ($zone_type == "SLAVE" || $perm_content_edit == "none" || ($perm_content_edit == "own" || $perm_content_edit == "own_as_client") && !$user_is_zone_owner) {
        error(ERR_PERM_ADD_RECORD);
    } else {
        // a PTR-record is added if an A or an AAAA-record are created
        // and checkbox is checked

        if ((isset($_POST["reverse"])) && $iface_add_reverse_record ) {
            if ($type === 'A') {
                $content_array = preg_split("/\./", $content);
                $content_rev = sprintf("%d.%d.%d.%d.in-addr.arpa", $content_array[3], $content_array[2], $content_array[1], $content_array[0]);
                $zone_rev_id = DnsRecord::get_best_matching_zone_id_from_name($content_rev);
            } elseif ($type === 'AAAA') {
                $content_rev = DnsRecord::convert_ipv6addr_to_ptrrec($content);
                $zone_rev_id = DnsRecord::get_best_matching_zone_id_from_name($content_rev);
            }
            if (isset($zone_rev_id) && $zone_rev_id != -1) {
                $zone_name = DnsRecord::get_domain_name_by_id($zone_id);
                $fqdn_name = sprintf("%s.%s", $name, $zone_name);
                if (DnsRecord::add_record($zone_rev_id, $content_rev, 'PTR', $fqdn_name, $ttl, $prio)) {
                    success(" <a href=\"edit.php?id=" . $zone_rev_id . "\"> " . _('The PTR-record was successfully added.') . "</a>");
                    Syslog::log_info(sprintf('client_ip:%s user:%s operation:add_record record_type:PTR record:%s content:%s ttl:%s priority:%s',
                                      $_SERVER['REMOTE_ADDR'], $_SESSION["userlogin"],
                                      $content_rev, $fqdn_name, $ttl, $prio));
		    if ($pdnssec_use && Dnssec::dnssec_rectify_zone($zone_rev_id)) {
                success(SUC_EXEC_PDNSSEC_RECTIFY_ZONE);
            }

                }
            } elseif (isset($content_rev)) {
                error(sprintf(ERR_REVERS_ZONE_NOT_EXIST, $content_rev));
            }
        }
        if (DnsRecord::add_record($zone_id, $name, $type, $content, $ttl, $prio)) {
            success(" <a href=\"edit.php?id=" . $zone_id . "\"> " . _('The record was successfully added.') . "</a>");
            Syslog::log_info(sprintf('client_ip:%s user:%s operation:add_record record_type:%s record:%s.%s content:%s ttl:%s priority:%s',
                $_SERVER['REMOTE_ADDR'], $_SESSION["userlogin"],
                $type, $name, $zone_name, $content, $ttl, $prio)
            );

            if ($pdnssec_use && Dnssec::dnssec_rectify_zone($zone_id)) {
                success(SUC_EXEC_PDNSSEC_RECTIFY_ZONE);
            }

            $name = $type = $content = $ttl = $prio = "";
        }
    }
}

if ($zone_type == "SLAVE" || $perm_content_edit == "none" || ($perm_content_edit == "own" || $perm_content_edit == "own_as_client") && !$user_is_zone_owner) {
    error(ERR_PERM_ADD_RECORD);
    include_once('inc/footer.inc.php');
    exit;
}

echo "    <h2>" . _('Add record to zone') . " <a href=\"edit.php?id=" . $zone_id . "\"> " . $zone_name . "</a></h2>\n";
echo "     <form method=\"post\">\n";
echo "      <input type=\"hidden\" name=\"domain\" value=\"" . $zone_id . "\">\n";
echo "      <table border=\"0\" cellspacing=\"4\">\n";
echo "       <tr>\n";
echo "        <td class=\"n\">" . _('Name') . "</td>\n";
echo "        <td class=\"n\">&nbsp;</td>\n";
echo "        <td class=\"n\">" . _('Type') . "</td>\n";
echo "        <td class=\"n\">" . _('Content') . "</td>\n";
echo "        <td class=\"n\">" . _('Priority') . "</td>\n";
echo "        <td class=\"n\">" . _('TTL') . "</td>\n";
echo "       </tr>\n";
echo "       <tr>\n";
echo "        <td class=\"n\"><input type=\"text\" name=\"name\" class=\"input\" value=\"" . htmlspecialchars($name) . "\">." . $zone_name . "</td>\n";
echo "        <td class=\"n\">IN</td>\n";
echo "        <td class=\"n\">\n";
echo "         <select name=\"type\">\n";
$found_selected_type = !(isset($type) && $type);
foreach (RecordType::getTypes() as $record_type) {
    if (isset($type) && $type) {
        if ($type == $record_type) {
            $found_selected_type = true;
            $add = " SELECTED";
        } else {
            $add = "";
        }
    } else {
        if (preg_match('/i(p6|n-addr).arpa/i', $zone_name) && strtoupper($record_type) == 'PTR') {
            $add = " SELECTED";
            $rev = "";
        } elseif ((strtoupper($record_type) == 'A') && $iface_add_reverse_record) {
            $add = " SELECTED";
            $rev = "<input type=\"checkbox\" name=\"reverse\"><span class=\"normaltext\">" . _('Add also reverse record') . "</span>\n";
        } else {
            $add = "";
        }
    }
    echo "          <option" . $add . " value=\"" . htmlspecialchars($record_type) . "\">" . $record_type . "</option>\n";
}
if (!$found_selected_type) {
    echo "          <option SELECTED value=\"" . htmlspecialchars($type) . "\"><i>" . htmlspecialchars($type) . "</i></option>\n";
}
echo "         </select>\n";
echo "        </td>\n";
echo "        <td class=\"n\"><input type=\"text\" name=\"content\" class=\"input\" value=\"" . htmlspecialchars($content) . "\"></td>\n";
echo "        <td class=\"n\"><input type=\"text\" name=\"prio\" class=\"sinput\" value=\"" . htmlspecialchars($prio) . "\"></td>\n";
echo "        <td class=\"n\"><input type=\"text\" name=\"ttl\" class=\"sinput\" value=\"" . htmlspecialchars($ttl) . "\"</td>\n";
echo "       </tr>\n";
echo "      </table>\n";
echo "      <br>\n";
echo "      <input type=\"submit\" name=\"commit\" value=\"" . _('Add record') . "\" class=\"button\">\n";
if (isset($rev)) {
    echo "      $rev";
}
echo "     </form>\n";

include_once('inc/footer.inc.php');
