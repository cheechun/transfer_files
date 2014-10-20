<?php defined("SYSPATH") or die("No direct script access.") ?>
<?= $theme->css("transfer_files.css") ?>
<?= $theme->css("jquery.autocomplete.css") ?>
<?= $theme->script("jquery.autocomplete.js") ?>
<script type="text/javascript">
$("document").ready(function() {
/* only works with lowercase?
  $("#g-path").gallery_autocomplete(
    "<?= url::site("__ARGS__") ?>".replace("__ARGS__", "admin/transfer_files/autocomplete"),
    {
      max: 256,
      loadingClass: "g-loading-small",
    });
  $("#g-move-path").gallery_autocomplete(
    "<?= url::site("__ARGS__") ?>".replace("__ARGS__", "admin/transfer_files/autocomplete"),
    {
      max: 256,
      loadingClass: "g-loading-small",
    });
*/
});
</script>

<div class="g-block">
  <h1> <?= t("Transfer Files administration") ?> </h1>
  <div class="g-block-content">
    <?= $form ?>
    <h2><?= t("Authorized paths") ?></h2>
    <ul id="g-transfer_files-paths">
      <? if (empty($paths)): ?>
      <li class="g-module-status g-info"><?= t("No authorized image source paths defined yet") ?></li>
      <? endif ?>

      <table>
      <tr><th>Source</th><th>Album</th><th>Move to after processing (Not ready)<th><th>Delete</th></tr>
      <? foreach ($path_entries as $sourcepath => $target): ?>
        <tr> 
          <td><?= html::clean($sourcepath) ?> </td>
          <td> <?= html::clean($target[0]) ?> </td>
          <td> <?= html::clean($target[1]) ?> </td>
          <td>
            <a href="<?= url::site("admin/transfer_files/remove_path?path=" . urlencode($sourcepath) . "&amp;csrf=" . access::csrf_token()) ?>"
             id="icon_<?= $id ?>"
             class="g-remove-dir g-button">
            <span class="ui-icon ui-icon-trash">
              <?= t("delete") ?>
            </span>
            </a>
          </td>
        </tr>
      <? endforeach ?>

      </table>
    </ul>
    <?= $form_additional ?>
  </div>
</div>
