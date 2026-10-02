<div class="d-flex flex-wrap align-items-center gap-2 mb-2">
	<div class="btn-group" role="group">{role_states}</div>
</div>

<form action="{page_url}" method="post">
	<input type="hidden" name="state" value="{state}">
	<input type="hidden" name="_token" value="{csrf_token}">

	<div class="table-responsive">
		<table class="table table-sm table-hover align-middle mb-0 xnova-overview-table">
			<thead>
				<tr>
					<th scope="col">{acl_hdr_order}</th>
					<th scope="col">{acl_hdr_label}</th>
					<th scope="col">{acl_hdr_name}</th>
					<th scope="col">{acl_hdr_description}</th>
					<th scope="col" class="text-end">{acl_hdr_users}</th>
					<th scope="col" class="text-end">{acl_hdr_permissions}</th>
					<th scope="col" class="text-end">{acl_hdr_action}</th>
				</tr>
			</thead>
			<tbody>
				<tr class="{role_rows_empty_class}">
					<td colspan="7" class="text-body-secondary">{acl_no_role}</td>
				</tr>
				{role_rows}
			</tbody>
		</table>
	</div>
</form>

<div class="alert alert-info d-flex align-items-center gap-2 mt-3 mb-0" role="alert">
	<i class="bi bi-info-circle" aria-hidden="true"></i>
	<span>{acl_super_admin_note}<br>{acl_order_note}</span>
</div>

{role_form}
