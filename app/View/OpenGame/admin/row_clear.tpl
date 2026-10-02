<form action="{clear_action}" method="post" class="d-inline">
	<input type="hidden" name="do" value="clear">
	<input type="hidden" name="_token" value="{clear_token}">
	<button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('{clear_confirm}')">
		<i class="bi bi-trash3" aria-hidden="true"></i> {clear_label}
	</button>
</form>
