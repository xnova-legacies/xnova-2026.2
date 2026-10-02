<form action="{delete_action}" method="post" class="d-inline">
	<input type="hidden" name="do" value="delete">
	<input type="hidden" name="id" value="{delete_id}">
	<input type="hidden" name="_token" value="{delete_token}">
	<button type="submit" class="btn btn-sm btn-outline-danger py-0 px-1" title="{delete_label}" onclick="return confirm('{delete_confirm}')">
		<i class="bi bi-trash" aria-hidden="true"></i>
		<span class="visually-hidden">{delete_label}</span>
	</button>
</form>
