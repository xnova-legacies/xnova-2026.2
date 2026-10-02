<div class="card xnova-panel border-0 shadow-sm mb-3">
	<div class="card-header fw-semibold">
		<i class="bi bi-people" aria-hidden="true"></i> {Declaration}
	</div>
	<form method="post" action="{multi_action}">
		<div class="card-body">
			<p class="text-body-secondary">{DeclarationText}</p>
			<label class="visually-hidden" for="texte">{Declaration}</label>
			<textarea class="form-control" name="texte" id="texte" rows="6"></textarea>
		</div>
		<div class="card-footer text-end">
			<button type="submit" class="btn btn-primary">
				<i class="bi bi-send" aria-hidden="true"></i> {multi_send}
			</button>
		</div>
	</form>
</div>
