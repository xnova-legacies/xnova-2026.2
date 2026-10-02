<form action="{queue_action}" method="post" class="d-flex flex-column gap-3">
	<p class="mb-0 small text-body-secondary">{adm_cleaner_hint}</p>
	<div>
		<button class="btn btn-sm btn-primary" type="submit" name="run" value="1">
			<i class="bi bi-tools" aria-hidden="true"></i> {adm_cleaner_btn}
		</button>
	</div>
	<input type="hidden" name="_token" value="{csrf_token}">
</form>
