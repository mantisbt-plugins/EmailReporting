function ERP_setVisible(element, visible) {
	element.classList.toggle('hidden', !visible);
}

function ERP_matchesRule(controlValue, rule) {

	// Equality (IN)
	if (Array.isArray(rule)) {
		return rule.includes(controlValue);
	}

	// Not equal (NOT IN)
	if (rule['!=']) {
		return !rule['!='].includes(controlValue);
	}

	return false;
}

function ERP_updateFields() {

	document
		.querySelectorAll('[data-visible-when]')
		.forEach(function(element) {

			let rules;

			try {
				rules = JSON.parse(
					element.dataset.visibleWhen
				);
			}
			catch (e) {
				console.error(
					'Invalid data-visible-when:',
					element
				);

				ERP_setVisible(element, true);
				return;
			}

			const visible = Object.entries(rules).every(
				function([fieldId, rule]) {

					const field =
						document.getElementById(fieldId);

					if (!field) {
						console.warn(
							'Field not found:',
							fieldId
						);

						return false;
					}

					return ERP_matchesRule(
						field.value,
						rule
					);
				}
			);

			ERP_setVisible(element, visible);
		});
}


document
	.querySelectorAll(
		'[data-visibility-controller]'
	)
	.forEach(element => {
		element.addEventListener(
			'change',
			ERP_updateFields
		);
	});

window.addEventListener('pageshow', ERP_updateFields); // Update for specific events

ERP_updateFields(); // Set correct state on page load
