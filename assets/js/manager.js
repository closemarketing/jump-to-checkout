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
		document.querySelectorAll('.jump-to-checkout-copy-url').forEach(function(btn) {
			btn.addEventListener('click', handleCopyUrl);
		});

		// Delete link buttons.
		document.querySelectorAll('.jump-to-checkout-delete-link').forEach(function(btn) {
			btn.addEventListener('click', handleDeleteLink);
		});

		// Toggle status buttons.
		document.querySelectorAll('.jump-to-checkout-toggle-status').forEach(function(btn) {
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
				alert(jptcManager.i18n.copy_error);
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

		if (!confirm(jptcManager.i18n.confirm_delete)) {
			return;
		}

		const row = e.target.closest('tr');
		const data = new FormData();
		data.append('action', 'jptc_delete_link');
		data.append('nonce', jptcManager.nonce);
		data.append('link_id', linkId);

		fetch(jptcManager.ajax_url, {
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
					const tbody = document.querySelector('.jump-to-checkout-links-table tbody');
					if (tbody && tbody.children.length === 0) {
						tbody.innerHTML = '<tr class="no-items"><td colspan="9">' + 
							jptcManager.i18n.no_links + '</td></tr>';
					}
				}, 300);
			} else {
				alert(response.data.message || jptcManager.i18n.delete_error);
			}
		})
		.catch(function(error) {
			console.error('Error:', error);
			alert(jptcManager.i18n.delete_error);
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
		data.append('action', 'jptc_toggle_status');
		data.append('nonce', jptcManager.nonce);
		data.append('link_id', linkId);
		data.append('status', status);

		fetch(jptcManager.ajax_url, {
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
				const statusBadge = row.querySelector('.jump-to-checkout-status');
				
				// Update status badge.
				statusBadge.className = 'jump-to-checkout-status jump-to-checkout-status-' + newStatus;
				statusBadge.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
				
				// Update button.
				e.target.setAttribute('data-status', newStatus);
				e.target.textContent = newStatus === 'active' ? 
					jptcManager.i18n.disable : 
					jptcManager.i18n.enable;
			} else {
				alert(response.data.message || jptcManager.i18n.status_error);
			}
		})
		.catch(function(error) {
			console.error('Error:', error);
			alert(jptcManager.i18n.status_error);
		});
	}

	/**
	 * Show success message
	 */
	function showSuccess(button) {
		const originalText = button.textContent;
		button.textContent = jptcManager.i18n.copied;
		button.disabled = true;

		setTimeout(function() {
			button.textContent = originalText;
			button.disabled = false;
		}, 2000);
	}
})();

