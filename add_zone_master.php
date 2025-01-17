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
 * Script that handles requests to add new master zones
 *
 * @package     Poweradmin
 * @copyright   2007-2010 Rejo Zenger <rejo@zenger.nl>
 * @copyright   2010-2022  Poweradmin Development Team
 * @license     https://opensource.org/licenses/GPL-3.0 GPL
 */

use Poweradmin\AppFactory;
use Poweradmin\Dns;
use Poweradmin\DnsRecord;
use Poweradmin\Dnssec;
use Poweradmin\Syslog;
use Poweradmin\Validation;
use Poweradmin\ZoneTemplate;
use Poweradmin\ZoneType;

require_once 'inc/toolkit.inc.php';
require_once 'inc/message.inc.php';

include_once 'inc/header.inc.php';

$app = AppFactory::create();
$pdnssec_use = $app->config('pdnssec_use');
$dns_third_level_check = $app->config('dns_third_level_check');
$iface_zone_type_default = $app->config('iface_zone_type_default');

$owner = "-1";
if ((isset($_POST['owner'])) && (Validation::is_number($_POST['owner']))) {
    $owner = $_POST['owner'];
}

$dom_type = "NATIVE";
if (isset($_POST["dom_type"]) && (in_array($_POST['dom_type'], ZoneType::getTypes()))) {
    $dom_type = $_POST["dom_type"];
}

$zone = "";
if (isset($_POST['domain'])) {
    $zone = idn_to_ascii(trim($_POST['domain']), IDNA_NONTRANSITIONAL_TO_ASCII);
}

$zone_template = $_POST['zone_template'] ?? "none";
$enable_dnssec = isset($_POST['dnssec']) && $_POST['dnssec'] == '1';

$zone_master_add = do_hook('verify_permission', 'zone_master_add');
$perm_view_others = do_hook('verify_permission', 'user_view_others');

if (isset($_POST['submit']) && $zone_master_add) {
    if (!Dns::is_valid_hostname_fqdn($zone, 0)) {
        error($zone . ' failed - ' . ERR_DNS_HOSTNAME);
    } elseif ($dns_third_level_check && DnsRecord::get_domain_level($zone) > 2 && DnsRecord::domain_exists(DnsRecord::get_second_level_domain($zone))) {
        error($zone . ' failed - ' . ERR_DOMAIN_EXISTS);
    } elseif (DnsRecord::domain_exists($zone) || DnsRecord::record_name_exists($zone)) {
        error($zone . ' failed - ' . ERR_DOMAIN_EXISTS);
        // TODO: repopulate domain name(s) to the form if there was an error occured
    } elseif (DnsRecord::add_domain($zone, $owner, $dom_type, '', $zone_template)) {
        $domain_id = DnsRecord::get_zone_id_from_name($zone);
        success("<a href=\"edit.php?id=" . $domain_id . "\">" . $zone . " - " . SUC_ZONE_ADD . '</a>');
        Syslog::log_info(sprintf('client_ip:%s user:%s operation:add_zone zone:%s zone_type:%s zone_template:%s',
            $_SERVER['REMOTE_ADDR'], $_SESSION["userlogin"],
            $zone, $dom_type, $zone_template));

        if ($pdnssec_use) {
            if ($enable_dnssec) {
                Dnssec::dnssec_secure_zone($zone);
            }

            Dnssec::dnssec_rectify_zone($domain_id);
        }

        unset($zone, $owner, $dom_type, $zone_template);
    }
}

if (!$zone_master_add) {
    error(ERR_PERM_ADD_ZONE_MASTER);
    include_once('inc/footer.inc.php');
    exit;
}

echo "     <h2>" . _('Add master zone') . "</h2>\n";

$available_zone_types = array("MASTER", "NATIVE");
$users = do_hook('show_users');
$zone_templates = ZoneTemplate::get_list_zone_templ($_SESSION['userid']);

echo "     <form method=\"post\" action=\"add_zone_master.php\">\n";
echo "      <table>\n";
echo "       <tr>\n";
echo "        <td class=\"n\" width=\"150\">" . _('Zone name') . ":</td>\n";
echo "        <td class=\"n\">\n";
echo "         <ul id=\"domain_names\" style=\"list-style-type:none; padding:0 \">\n";
echo "          <li><input type=\"text\" class=\"input\" name=\"domain\" value=\"\"></li>\n";
echo "         </ul>\n";
echo "        </td>\n";
echo "       </tr>\n";
echo "       <tr>\n";
echo "        <td class=\"n\">" . _('Owner') . ":</td>\n";
echo "        <td class=\"n\">\n";
echo "         <select name=\"owner\">\n";
/*
  Display list of users to assign zone to if creating
  user has the proper permission to do so.
 */
foreach ($users as $user) {
    if ($user['id'] === $_SESSION['userid']) {
        echo "          <option value=\"" . $user['id'] . "\" selected>" . $user['fullname'] . "</option>\n";
    } elseif ($perm_view_others) {
        echo "          <option value=\"" . $user['id'] . "\">" . $user['fullname'] . "</option>\n";
    }
}
echo "         </select>\n";
echo "        </td>\n";
echo "       </tr>\n";
echo "       <tr>\n";
echo "        <td class=\"n\">" . _('Type') . ":</td>\n";
echo "        <td class=\"n\">\n";
echo "         <select name=\"dom_type\">\n";
foreach ($available_zone_types as $type) {
    $type == $iface_zone_type_default ? $selected = ' selected' : $selected = '';
    echo "          <option value=\"" . $type . "\" $selected>" . strtolower($type) . "</option>\n";
}
echo "         </select>\n";
echo "        </td>\n";
echo "       </tr>\n";
echo "       <tr>\n";
echo "        <td class=\"n\">" . _('Template') . ":</td>\n";
echo "        <td class=\"n\">\n";
echo "         <select name=\"zone_template\">\n";
echo "          <option value=\"none\">none</option>\n";
foreach ($zone_templates as $zone_template) {
    echo "          <option value=\"" . $zone_template['id'] . "\">" . $zone_template['name'] . "</option>\n";
}
echo "         </select>\n";
echo "        </td>\n";
echo "       </tr>\n";
echo "       <tr>\n";
echo "        <td class=\"n\">" . _('DNSSEC') . ":</td>\n";
echo "        <td class=\"n\"><input type=\"checkbox\" class=\"input\" name=\"dnssec\" value=\"1\" " . ($pdnssec_use ? "checked" : "") . "></td>\n";
echo "       </tr>\n";
echo "       <tr>\n";
echo "        <td class=\"n\">&nbsp;</td>\n";
echo "        <td class=\"n\">\n";
echo "         <input type=\"submit\" class=\"button\" name=\"submit\" value=\"" . _('Add zone') . "\">\n";
echo "        </td>\n";
echo "       </tr>\n";
echo "      </table>\n";
echo "     </form>\n";

include_once('inc/footer.inc.php');
