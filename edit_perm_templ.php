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
 * Script that handles editing of permission templates
 *
 * @package     Poweradmin
 * @copyright   2007-2010 Rejo Zenger <rejo@zenger.nl>
 * @copyright   2010-2022  Poweradmin Development Team
 * @license     https://opensource.org/licenses/GPL-3.0 GPL
 */

use Poweradmin\AppFactory;
use Poweradmin\Validation;

require_once 'inc/toolkit.inc.php';
require_once 'inc/message.inc.php';

include_once 'inc/header.inc.php';

if (!isset($_GET['id']) && !Validation::is_number($_GET['id'])) {
    error(ERR_INV_INPUT);
    include_once('inc/footer.inc.php');
    exit;
}
$id = htmlspecialchars($_GET['id']);

if (!do_hook('verify_permission', 'templ_perm_edit')) {
    error(ERR_PERM_EDIT_PERM_TEMPL);
    include_once('inc/footer.inc.php');
    exit;
}

if (isset($_POST['commit'])) {
    do_hook('update_perm_templ_details', $_POST);
    success(SUC_RECORD_UPD);
}

$app = AppFactory::create();
$app->render('edit_perm_templ.html', [
    'id' => $id,
    'templ' => do_hook('get_permission_template_details', $id),
    'perms_templ' => do_hook('get_permissions_by_template_id', $id),
    'perms_avail' => do_hook('get_permissions_by_template_id')
]);


include_once('inc/footer.inc.php');
