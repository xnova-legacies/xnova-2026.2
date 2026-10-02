<script src="/scripts/cntchar.js"></script>
<div class="card xnova-panel border-0 shadow-sm mb-3">
	<div class="card-header fw-semibold">
		<i class="bi bi-envelope-paper" aria-hidden="true"></i> {Send_message}
	</div>
	<form action="/game/profil/messages?mode=write&id={id}" method="post" data-ajax="/game/api/messages/send" data-ajax-reload="1">
		<div class="card-body">
			<div class="row mb-3">
				<label class="col-sm-3 col-form-label" for="pm-to">{Recipient}</label>
				<div class="col-sm-9">
					<input type="text" class="form-control" id="pm-to" name="to" value="{to}" readonly>
				</div>
			</div>
			<div class="row mb-3">
				<label class="col-sm-3 col-form-label" for="pm-subject">{Subject}</label>
				<div class="col-sm-9">
					<input type="text" class="form-control" id="pm-subject" name="subject" maxlength="40" value="{subject}">
				</div>
			</div>
			<div class="row">
				<label class="col-sm-3 col-form-label" for="pm-text">
					{Message}
					<span class="text-body-secondary">(<span id="cntChars">0</span> / 5000 {characters})</span>
				</label>
				<div class="col-sm-9">
					<textarea class="form-control" id="pm-text" name="text" rows="10" oninput="cntchar(5000)">{text}</textarea>
				</div>
			</div>
		</div>
		<div class="card-footer">
			<div class="d-flex flex-wrap justify-content-end gap-2 mb-2">
				<button type="reset" class="btn btn-outline-secondary">
					<i class="bi bi-eraser" aria-hidden="true"></i> {mess_reset}
				</button>
				<button type="submit" class="btn btn-primary" onclick="this.form.submit(); this.disabled=true; document.getElementById('pm-submit-label').textContent='{mess_wait}';">
					<i class="bi bi-send" aria-hidden="true"></i> <span id="pm-submit-label">{Envoyer}</span>
				</button>
			</div>
			<p class="mb-0">
				<button class="btn btn-link btn-sm p-0 text-decoration-none" type="button" data-bs-toggle="collapse" data-bs-target="#pm-bbcode-help" aria-expanded="false" aria-controls="pm-bbcode-help">
					<i class="bi bi-question-circle" aria-hidden="true"></i> {mess_bbcode_toggle}
				</button>
			</p>
			<div class="collapse mt-2 text-body-secondary" id="pm-bbcode-help">{mess_bbcode_help}</div>
		</div>
	</form>
</div>
