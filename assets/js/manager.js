(function() {
	'use strict';

	document.addEventListener('DOMContentLoaded', function() {
		initEventListeners();
	});

	/**
	 * Initialize event listeners
	 */
	function initEventListeners() {
		// Copy URL buttons.
		document.querySelectorAll('.cldc-copy-url').forEach(function(btn) {
			btn.addEventListener('click', handleCopyUrl);
		});

		// Delete link buttons.
		document.querySelectorAll('.cldc-delete-link').forEach(function(btn) {
			btn.addEventListener('click', handleDeleteLink);
		});

		// Toggle status buttons.
		document.querySelectorAll('.cldc-toggle-status').forEach(function(btn) {
			btn.addEventListener('click', handleToggleStatus);
		});
	}

	/**
	 * Handle copy URL
	 */
	function handleCopyUrl(e) {
		const url = e.target.getAttribute('data-url');
		
		if (!url) {
			return;
		}

		// Create temporary input.
		const temp = document.createElement('input');
		temp.value = url;
		document.body.appendChild(temp);
		temp.select();
		temp.setSelectionRange(0, 99999);

		try {
			document.execCommand('copy');
			showSuccess(e.target);
		} catch (err) {
			// Fallback to Clipboard API.
			navigator.clipboard.writeText(url).then(function() {
				showSuccess(e.target);
			}).catch(function() {
				alert(cldcManager.i18n.copy_error);
			});
		}

		document.body.removeChild(temp);
	}

	/**
	 * Handle delete link
	 */
	function handleDeleteLink(e) {
		const linkId = e.target.getAttribute('data-link-id');
		
		if (!linkId) {
			return;
		}

		if (!confirm(cldcManager.i18n.confirm_delete)) {
			return;
		}

		const row = e.target.closest('tr');
		const data = new FormData();
		data.append('action', 'cldc_delete_link');
		data.append('nonce', cldcManager.nonce);
		data.append('link_id', linkId);

		fetch(cldcManager.ajax_url, {
			method: 'POST',
			body: data
		})
		.then(function(response) {
			return response.json();
		})
		.then(function(response) {
			if (response.success) {
				row.style.opacity = '0.5';
				setTimeout(function() {
					row.remove();
					// Check if table is empty.
					const tbody = document.querySelector('.cldc-links-table tbody');
					if (tbody && tbody.children.length === 0) {
						tbody.innerHTML = '<tr class="no-items"><td colspan="9">' + 
							cldcManager.i18n.no_links + '</td></tr>';
					}
				}, 300);
			} else {
				alert(response.data.message || cldcManager.i18n.delete_error);
			}
		})
		.catch(function(error) {
			console.error('Error:', error);
			alert(cldcManager.i18n.delete_error);
		});
	}

	/**
	 * Handle toggle status
	 */
	function handleToggleStatus(e) {
		const linkId = e.target.getAttribute('data-link-id');
		const status = e.target.getAttribute('data-status');
		
		if (!linkId || !status) {
			return;
		}

		const data = new FormData();
		data.append('action', 'cldc_toggle_status');
		data.append('nonce', cldcManager.nonce);
		data.append('link_id', linkId);
		data.append('status', status);

		fetch(cldcManager.ajax_url, {
			method: 'POST',
			body: data
		})
		.then(function(response) {
			return response.json();
		})
		.then(function(response) {
			if (response.success) {
				// Update UI.
				const newStatus = response.data.new_status;
				const row = e.target.closest('tr');
				const statusBadge = row.querySelector('.cldc-status');
				
				// Update status badge.
				statusBadge.className = 'cldc-status cldc-status-' + newStatus;
				statusBadge.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
				
				// Update button.
				e.target.setAttribute('data-status', newStatus);
				e.target.textContent = newStatus === 'active' ? 
					cldcManager.i18n.disable : 
					cldcManager.i18n.enable;
			} else {
				alert(response.data.message || cldcManager.i18n.status_error);
			}
		})
		.catch(function(error) {
			console.error('Error:', error);
			alert(cldcManager.i18n.status_error);
		});
	}

	/**
	 * Show success message
	 */
	function showSuccess(button) {
		const originalText = button.textContent;
		button.textContent = cldcManager.i18n.copied || 'Copied!';
		button.disabled = true;

		setTimeout(function() {
			button.textContent = originalText;
			button.disabled = false;
		}, 2000);
	}
})();

