<?php defined("SYSPATH") or die("No direct script access.");
/**
 * Gallery - a web based photo album viewer and editor
 * Copyright (C) 2000-2013 Bharat Mediratta
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or (at
 * your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street - Fifth Floor, Boston, MA  02110-1301, USA.
 */
class transfer_files_Core {

  static function check_configs($path_entries=null) {
    if ($path_entries === null) {
      $path_entries = unserialize(module::get_var("transfer_files", "path_entries"));
    }
error_log("check_configs", 3, "/tmp/transfer_files.out");
    if (empty($path_entries)) {
      site_status::warning(
        t("Transfer Files needs configuration. <a href=\"%url\">Configure it now!</a>",
          array("url" => html::mark_clean(url::site("admin/transfer_files")))),
        "transfer_files_configuration");
    } else {
      site_status::clear("transfer_files_configuration");
    }
  }

  static function is_valid_path($path) {
    if (!is_readable($path) || is_link($path)) {
      return false;
    }

    $path_entries = unserialize(module::get_var("transfer_files", "path_entries"));
    foreach (array_keys($path_entries) as $valid_path) {
      if (strpos($path, $valid_path) === 0) {
        return true;
      }
    }
    return false;
  }
}
