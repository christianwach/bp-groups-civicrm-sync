{* template block that contains the new field *}
<div id="testfieldoptions">
<h3>{ts}BuddyPress Group Sync{/ts}</h3>
<table class="form-layout-compressed">
<tr>
  <td class="label"><label for="bpgroupscivicrmsynccreatefromnew">{ts}Create a BuddyPress Group{/ts}</label></td>
  <td>{$form.bpgroupscivicrmsynccreatefromnew.html}</td>
</tr>
</table>
</div>

{* reposition the above block after #someOtherBlock *}
<script type="text/javascript">
  // jQuery will not move an item unless it is wrapped
  cj('#testfieldoptions').insertBefore('.crm-group-form-block > .crm-submit-buttons:last');
</script>
