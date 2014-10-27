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
class Admin_Transfer_Files_Controller extends Admin_Controller {

  public function index() {
    $view = new Admin_View("admin.html");
    $view->page_title = t("Transfer Files");
    $view->content = new View("admin_transfer_files.html");
    $view->content->form = $this->_get_admin_form();
    $view->content->form_additional = $this->_get_admin_form_additional();

    $view->content->path_entries = $this->configuredPaths();

    transfer_files::check_config($path_entries);

    print $view;
  }

  public function add_path() {
    access::verify_csrf();

    $form = $this->_get_admin_form();
    $path_entries = unserialize(module::get_var("transfer_files", "path_entries", "a:0:{}"));
    if ($form->validate()) {

      $sourcepath = $form->add_path->sourcepath->value;
      $album = html_entity_decode($form->add_path->albumid->value);
      $movepath = $form->add_path->movepath->value;
transfer_files::verboselog("$sourcepath\n");
      if (is_link($sourcepath)) {
        $form->add_path->sourcepath->add_error("is_symlink", 1);
      } else if (!is_readable($sourcepath)) {
        $form->add_path->sourcepath->add_error("not_readable", 1);
      } else if (($movepath != "") && !is_writeable($movepath)) {
        $form->add_path->movepath->add_error("not_writeable", 1);
      } else {

        $path_entries[$sourcepath] = array( $album, $movepath );
        module::set_var("transfer_files", "path_entries", serialize($path_entries));

        message::success(t("Added path %path", array("path" => $sourcepath)));
        transfer_files::check_config($path_entries);
        url::redirect("admin/transfer_files");
      }
    }

    $view = new Admin_View("admin.html");
    $view->content = new View("admin_transfer_files.html");
    $view->content->form = $form;
    $view->content->path_entries = $this->configuredPaths();

    print $view;
  }

  public function remove_path() {
    access::verify_csrf();

    $path = Input::instance()->get("path");

    $path_entries = unserialize(module::get_var("transfer_files", "path_entries"));

    if (isset($path_entries[$path])) {
      unset($path_entries[$path]);

      message::success(t("Removed path %path", array("path" => $path)));

      module::set_var("transfer_files", "path_entries", serialize($path_entries));

      transfer_files::check_config($path_entries);
      url::redirect("admin/transfer_files");
    }
  }

  public function autocomplete() {
    $directories = array();

    $path_prefix = Input::instance()->get("q");
    foreach (glob("{$path_prefix}*") as $file) {
      if (is_dir($file) && !is_link($file)) {
        $directories[] = $file;
      }
    }

    ajax::response(implode("\n", $directories));
  }

  private function _get_admin_form() {
    $form = new Forge("admin/transfer_files/add_path", "", "post");
    $add_path = $form->group("add_path");
    $add_path->input("sourcepath")->label(t("Path"))->rules("required")->id("g-path")
      ->error_messages("not_readable", t("This directory is not readable by the webserver"))
      ->error_messages("is_symlink", t("Symbolic links are not allowed"));

    $subflistperm = $this->rootalbums_list("Root");
    $add_path->dropdown("albumid")
        ->label(t("Transfer to album"))
        ->options($subflistperm);

    $add_path->input("movepath")->label(t("Optional : Move Processed Files to (not ready yet)"))->id("g-move-path")
             ->error_messages("not_writeable", t("This directory is not writeable by the webserver"))
             ->error_messages("is_symlink", t("Symbolic links are not allowed"));

    $add_path->submit("add")->value(t("Add Path"));

    return $form;
  }

  public function save_options() {
    access::verify_csrf();
    $form = $this->_get_admin_form_additional();
    if($form->validate()) {
      $file_ext = strtolower($form->addition_options->file_ext->value);
      $file_ext = preg_replace('/^,/', "", $file_ext);
      $file_ext = preg_replace('/,[\s*,]*/', ",", $file_ext);
      module::set_var("transfer_files", "file_ext", $file_ext);
    }
    url::redirect("admin/transfer_files");
  }


  private function _get_admin_form_additional() {
    $form = new Forge("admin/transfer_files/save_options", "", "post",
                      array("id" => "g-transfer-files-admin-additional-form"));
    $group = $form->group("addition_options")->label(t("Additional options"));

    $input = $group->input("file_ext")->label(t("Valid File Extensions (comma separated)"))->id("g-file-ext")
                        ->value(module::get_var("transfer_files", "file_ext"));

    $group->submit("save")->value(t("Save"));

    return $form;
  }

  private function rootalbums_list($default_top_album="Root") {
    /* Generates the list of sub-albums in the root folder */
    if ($default_top_album=="Root")
      $sflist = array(1 =>t("Root album"));
    else
      $sflist = array(1 =>t("Parent album"));
   
//    $subalbums = ORM::factory("item")->where("type","=","album")->where("level","<","5")->find_all();
//    foreach ($subalbums as $album) {
//      $sflist[$album->id] = str_repeat("-", $album->level) . $album->title;
//    }

    $this->get_subalbums(1, $sflist);
   
    return $sflist;
  }

  private function get_subalbums($albumid, &$sflist) {
    $subalbums = ORM::factory("item")->where("type","=","album")->where("parent_id","=","$albumid")->find_all();

    foreach ($subalbums as $album) {
      $level = $album->level;
      $sflist[$album->id] = str_repeat("-", $level) . $album->title;
      if ($level <=3)
        $this->get_subalbums($album->id, $sflist);
    }
  }

  private function configuredPaths(){

    $pathmap = unserialize(module::get_var("transfer_files", "path_entries", "a:0:{}"));

    foreach($pathmap as $sourcepath => $settings){
      $albumid = $settings[0];
      if ($albumid == 1)
        $pathmap[$sourcepath][0] = "Root Album";
      else {
        $album = ORM::factory("item", $albumid); 
        if ($album->loaded()){
          $pathmap[$sourcepath][0] = $album->name;
        } else {
          $pathmap[$sourcepath][0] = "unknown album";
        }
      }
    }
    return $pathmap;
  }


}
