<?php defined("SYSPATH") or die("No direct script access.");
/**
 * Gallery - a web based photo album viewer and editor
 * Copyright (C) 2000-2012 Bharat Mediratta
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
class Transfer_Files_Controller extends Controller {

  static $process_ext;

  static function cron()
  {
    $owner_id = 2;
	
    // Login as Admin
    print "Starting user session\n";
    $session = Session::instance();
    $session->delete("user");
    auth::login(IdentityProvider::instance()->admin_user());

    $paths = unserialize(module::get_var("transfer_files", "path_entries", "a:0:{}"));

    $exts = module::get_var("transfer_files", "file_ext", "");
    if ($exts != "")
      self::$process_ext = array_map('trim', explode(",", $exts));

    foreach ($paths as $sourcepath => $settings){
      $albumid = $settings[0];
      $movepath = $settings[1];
    // Validate albumid and sourcepath
      $foundAlbum = ORM::factory("item")
               ->where("type", "=", "album")
               ->where("id", "=", $albumid)
               ->count_all();
      if ($foundAlbum != 1){
        continue;
      }

      $baseAlbum = ORM::factory("item")
               ->where("type", "=", "album")
               ->where("id", "=", $albumid)
               ->find();
      if (is_dir($sourcepath)){
        self::transfer($sourcepath, $baseAlbum, $movepath);
      } else {
        continue;
      }
      
    }
  }

  /*********************************************************** 
     Transfer all files from $directory to $baseAlbum
     photos will be categorized by yyyy mm
     movies go to Movies subdirectory
  ************************************************************/
  static function transfer($directory, $baseAlbum, $movedir){
    // Get all files and filter out . and .. 
    $paths = scandir($directory);
    $bad = array(".", "..");
    $paths = array_diff($paths, $bad); 

    foreach ($paths as $path){
      $fullpath = $directory . DIRECTORY_SEPARATOR . $path;
      $movedest = $movedir;
      // if subdirectory call transfer recursively
      if (is_dir($fullpath)){ 
        transfer_files::verboselog("Processing $fullpath \n"); 
        // create move destination path
        if ($movedir != "") {
          // Create subdirs as per source.  Not implemented yet
//          $movedest = $movedir . DIRECTORY_SEPARATOR . $path;
//          if (!is_dir($movedest)){
//            mkdir($movedest, 0770);
//            chown($movedest, fileowner($fullpath));
//            chgrp($movedest, filegroup($fullpath));
//          }
        }

        self::transfer($fullpath, $baseAlbum, $movedest); 
        continue;   // process next item
      } else {
        transfer_files::verboselog("Transfering $fullpath and move to $movedir");
        // check validity of extensions
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        // check that file is within requested list of extensions
        if (!in_array($ext, self::$process_ext)){
          transfer_files::verboselog(" - not in the list of extension configured \n");
          continue;
        }

        if (!legal_file::get_extensions($ext)) {
          transfer_files::verboselog(" - gallery cannot handle this filetype \n");
          continue;   // process next item
        }
      }

      $basealbumid = $baseAlbum->id;
      $year = substr($path, 0, 4);
      $month = substr($path, 5, 2);

      $yearAlbum = self::getSubAlbum($baseAlbum, $year);
      $curAlbum = self::getSubAlbum($yearAlbum, $month);

      $basealbumid = $curAlbum->id;

      // Find if file already exists, if filename not changed
      $foundFile = ORM::factory("item")
               ->where("name", "=", $path)
               ->where("parent_id","=",$basealbumid)
               ->count_all();
      if ($foundFile > 0){
        transfer_files::verboselog(" - already exist\n");
        self::moveOrigFile($fullpath, $movedest); 
        continue;   // process next item
      }
      // Create new item
      $title = item::convert_filename_to_title($path);
  
      $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
      $newitem = ORM::factory("item");
      if (legal_file::get_photo_extensions($extension)) {
        $newitem->type = "photo";
      } else if (legal_file::get_movie_extensions($extension)) {
        $newitem->type = "movie";
      }

      // create new item
      try {
        $newitem->parent_id = $basealbumid;
        $newitem->set_data_file($fullpath);
        $newitem->name = $path;
        $newitem->title = $title;
        $newitem->owner_id = $curAlbum->owner_id;
        $newitem->save();
        $result = true;
        transfer_files::verboselog(" - Created new item $newitem->id \n");
      } catch (ORM_Validation_Exception $e) {
        foreach ($e->validation->errors() as $key => $error) {
          transfer_files::verboselog("ERR: transfer error $key $error\n");
        }
        continue;
      }
      self::moveOrigFile($fullpath, $movedest); 
    } // foreach
  }

  static function moveOrigFile($filepath, $destdir) {
    // move original file to another folder
    if (is_writable($destdir) && is_writable($filepath)){
      $destfile = $destdir . DIRECTORY_SEPARATOR . basename($filepath); 
      rename ($filepath, $destfile); 
transfer_files::verboselog("  renamed $filepath to $destfile\n");
    }

  }

  static function getSubAlbum($parentAlbum, $name){
      
    $basealbumid = $parentAlbum->id;
    // Find if subalbum exist
    $subAlbum = ORM::factory("item")
               ->where("type", "=", "album")
               ->where("slug", "=", $name)
               ->where("parent_id","=",$basealbumid)
               ->find();
    if ($subAlbum && $subAlbum->loaded()){
      return $subAlbum;
    } else {
      // We couldn't find the subalbum so we must create it
      try {
        $album = ORM::factory("item");
        $album->type = "album";
        $album->parent_id = $parentAlbum->id;
        $album->name = strval($name);
        $album->title = strval($name);
        $album->slug = strval($name);
        $album->owner_id = $parentAlbum->owner_id;
        $album->sort_order = $parentAlbum->sort_order;
        $album->sort_column = $parentAlbum->sort_column;
        $album->save();
      } catch (ORM_Validation_Exception $e) {
        // Translate ORM validation errors into form error messages
        // calendarimport::log_event("Failed to create album. The error will appear in the next entry",2,10022);
        foreach ($e->validation->errors() as $key => $error) {
          transfer_files::verboselog("subalbum_create error $key $error\n");
        }
        return NULL;
      }
    }

    return $album;

  }

}
