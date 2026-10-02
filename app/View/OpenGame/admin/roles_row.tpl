<tr class="xnova-role-row{role_row_class}">
	<td class="text-nowrap">
		<button class="btn btn-sm btn-outline-secondary py-0 px-1{role_row_up_class}" type="submit" name="moveup" value="{role_row_id}" title="{acl_move_up}">
			<i class="bi bi-arrow-up" aria-hidden="true"></i>
			<span class="visually-hidden">{acl_move_up}</span>
		</button>
		<button class="btn btn-sm btn-outline-secondary py-0 px-1{role_row_down_class}" type="submit" name="movedown" value="{role_row_id}" title="{acl_move_down}">
			<i class="bi bi-arrow-down" aria-hidden="true"></i>
			<span class="visually-hidden">{acl_move_down}</span>
		</button>
		<span class="text-body-secondary small ms-1">{role_row_position}</span>
	</td>
	<td class="fw-semibold">{role_row_label}</td>
	<td class="small text-body-secondary">{role_row_name}</td>
	<td class="small text-break">{role_row_description}</td>
	<td class="text-end small">{role_row_users}</td>
	<td class="text-end small">{role_row_permissions}</td>
	<td class="text-end text-nowrap">
		<a class="btn btn-sm btn-outline-primary py-0 px-1{role_row_edit_class}" href="{role_action}?id={role_row_id}" title="{acl_edit}">
			<i class="bi bi-pencil" aria-hidden="true"></i>
			<span class="visually-hidden">{acl_edit}</span>
		</a>
		<button class="btn btn-sm btn-outline-danger py-0 px-1{role_row_del_class}" type="submit" name="delid" value="{role_row_id}" title="{acl_delete}">
			<i class="bi bi-trash" aria-hidden="true"></i>
			<span class="visually-hidden">{acl_delete}</span>
		</button>
		<button class="btn btn-sm btn-outline-success py-0 px-1{role_row_rest_class}" type="submit" name="restid" value="{role_row_id}" title="{acl_restore}">
			<i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
			<span class="visually-hidden">{acl_restore}</span>
		</button>
		<span class="badge text-bg-secondary{role_row_default_class}">{acl_default_badge}</span>
		<span class="badge text-bg-secondary{role_row_above_class}">{acl_above_badge}</span>
		<span class="badge text-bg-warning{role_row_used_class}">{acl_used_badge}</span>
	</td>
</tr>
