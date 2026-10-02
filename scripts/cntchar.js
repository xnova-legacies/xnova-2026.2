// Compteur de caracteres pour les formulaires (textarea ou input nomme "text").
// Utilise par messages_pm_form.tpl et la demande d'ami (buddy).
var x = "";

function cntchar(m) {
	var field = document.querySelector('textarea[name="text"], input[name="text"]');

	if (!field) {
		var form = window.document.forms[0];
		field = form ? form.text : null;
	}

	if (!field) {
		return;
	}

	if (field.value.length > m) {
		field.value = x;
	} else {
		x = field.value;
	}

	var counter = document.getElementById('cntChars');
	if (counter) {
		if (counter.firstChild) {
			counter.firstChild.data = field.value.length;
		} else {
			counter.appendChild(document.createTextNode(field.value.length));
		}
	}
}
