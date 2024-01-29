{* template block that contains the new field *}
<div id="bpgcs-block">
<h3>{$bpgcs_title}</h3>
<table class="form-layout">
<tr>
  <td colspan="2"><p style="color: red;">{$bpgcs_description}</p></td>
</tr>
<tr>
  <td class="label"><label for="bpgcs_create_from_og">{$bpgcs_label}</label></td>
  <td>{$form.bpgcs_create_from_og.html}</td>
</tr>
</table>
</div>

{* reposition the above block after #someOtherBlock *}
<script type="text/javascript">
  // jQuery will not move an item unless it is wrapped.
  cj('#bpgcs-block').insertBefore('.crm-group-form-block > .crm-submit-buttons:last');
</script>
