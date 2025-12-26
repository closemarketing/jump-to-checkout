(function() {
	'use strict';

	const selectedProducts = [];

	document.addEventListener('DOMContentLoaded', function() {
		initSelect2();
		initEventListeners();
	});

	/**
	 * Initialize Select2 for product search
	 */
	function initSelect2() {
		if (typeof jQuery === 'undefined' || typeof jQuery.fn.select2 === 'undefined') {
			console.error('jQuery or Select2 not loaded');
			return;
		}

		jQuery('.jump-to-checkout-product-search').select2({
			ajax: {
				url: jptcAdmin.ajax_url,
				dataType: 'json',
				delay: 250,
				data: function(params) {
					return {
						q: params.term,
						action: 'jptc_search_products',
						nonce: jptcAdmin.nonce
					};
				},
				processResults: function(data) {
					return {
						results: data.results || []
					};
				},
				cache: true
			},
			minimumInputLength: 2,
			placeholder: jptcAdmin.i18n.search_placeholder
		});
	}

	/**
	 * Initialize event listeners
	 */
	function initEventListeners() {
		const addProductBtn = document.querySelector('.jump-to-checkout-add-product');
		const generateLinkBtn = document.querySelector('.jump-to-checkout-generate-link');
		const copyLinkBtn = document.querySelector('.jump-to-checkout-copy-link');
		const expiryRadios = document.querySelectorAll('input[name="jptc_expiry_type"]');
		const expiryHoursInput = document.querySelector('input[name="jptc_expiry_hours"]');

		if (addProductBtn) {
			addProductBtn.addEventListener('click', handleAddProduct);
		}

		if (generateLinkBtn) {
			generateLinkBtn.addEventListener('click', handleGenerateLink);
		}

		if (copyLinkBtn) {
			copyLinkBtn.addEventListener('click', handleCopyLink);
		}

		expiryRadios.forEach(function(radio) {
			radio.addEventListener('change', function() {
				if (expiryHoursInput) {
					expiryHoursInput.disabled = this.value !== 'custom';
				}
			});
		});
	}

	/**
	 * Handle add product
	 */
	function handleAddProduct() {
		const select = document.querySelector('.jump-to-checkout-product-search');
		const quantityInput = document.querySelector('.jump-to-checkout-quantity');

		if (!select || !quantityInput) {
			return;
		}

		const selectedOption = select.options[select.selectedIndex];
		
		if (!selectedOption || !selectedOption.value) {
			alert(jptcAdmin.i18n.no_products);
			return;
		}

		// Check FREE limitation: only 1 product per link.
		if (!jptcAdmin.is_pro && selectedProducts.length >= jptcAdmin.max_products) {
			if (confirm(jptcAdmin.i18n.max_products_reached + '\n\n' + jptcAdmin.i18n.upgrade_confirm)) {
				window.open(jptcAdmin.upgrade_url, '_blank');
			}
			return;
		}

		const productId = selectedOption.value;
		// Strip HTML tags from product name.
		const productName = stripHtml(selectedOption.text);
		const quantity = parseInt(quantityInput.value) || 1;

		// Check if product already exists.
		const existingIndex = selectedProducts.findIndex(function(p) {
			return p.product_id === productId;
		});

		if (existingIndex !== -1) {
			selectedProducts[existingIndex].quantity = quantity;
		} else {
			selectedProducts.push({
				product_id: productId,
				name: productName,
				quantity: quantity
			});
		}

		renderSelectedProducts();

		// Reset select2.
		jQuery('.jump-to-checkout-product-search').val(null).trigger('change');
		quantityInput.value = 1;
	}

	/**
	 * Render selected products table
	 */
	function renderSelectedProducts() {
		const tbody = document.querySelector('.jump-to-checkout-selected-products-body');
		
		if (!tbody) {
			return;
		}

		if (selectedProducts.length === 0) {
			tbody.innerHTML = '<tr class="no-items"><td colspan="3">' + escapeHtml(jptcAdmin.i18n.no_products_label) + '</td></tr>';
			return;
		}

		tbody.innerHTML = '';

		selectedProducts.forEach(function(product, index) {
			const row = document.createElement('tr');
			row.innerHTML = '<td class="jump-to-checkout-product-name">' + escapeHtml(product.name) + '</td>' +
				'<td>' + product.quantity + '</td>' +
				'<td><button type="button" class="button button-small jump-to-checkout-remove-product" data-index="' + 
				index + '">' + escapeHtml(jptcAdmin.i18n.remove_button) + '</button></td>';
			
			tbody.appendChild(row);
		});

		// Add event listeners to remove buttons.
		document.querySelectorAll('.jump-to-checkout-remove-product').forEach(function(btn) {
			btn.addEventListener('click', function() {
				const index = parseInt(this.getAttribute('data-index'));
				selectedProducts.splice(index, 1);
				renderSelectedProducts();
			});
		});
	}

	/**
	 * Handle generate link
	 */
	function handleGenerateLink() {
		const linkName = document.querySelector('.jump-to-checkout-link-name');
		
		if (!linkName || !linkName.value.trim()) {
			alert(jptcAdmin.i18n.no_link_name);
			linkName.focus();
			return;
		}

		if (selectedProducts.length === 0) {
			alert(jptcAdmin.i18n.no_products_selected);
			return;
		}

		const expiryType = document.querySelector('input[name="jptc_expiry_type"]:checked');
		const expiryHours = document.querySelector('input[name="jptc_expiry_hours"]');
		
		let expiry = 0;
		if (expiryType && expiryType.value === 'custom' && expiryHours) {
			expiry = parseInt(expiryHours.value) || 0;
		}

		const data = new FormData();
		data.append('action', 'jptc_generate_link');
		data.append('nonce', jptcAdmin.nonce);
		data.append('name', linkName.value.trim());
		data.append('products', JSON.stringify(selectedProducts));
		data.append('expiry', expiry);

		fetch(jptcAdmin.ajax_url, {
			method: 'POST',
			body: data
		})
		.then(function(response) {
			return response.json();
		})
		.then(function(response) {
			console.log('Response:', response); // Debug log.
			
			if (response.success) {
				const link = response.data.link;
				if (link) {
					displayGeneratedLink(link);
					// Reset form.
					linkName.value = '';
					selectedProducts.length = 0;
					renderSelectedProducts();
				} else {
					alert(jptcAdmin.i18n.no_link_in_response);
				}
			} else {
				const errorMessage = (response.data && response.data.message) ? response.data.message : jptcAdmin.i18n.generate_error;
				
				// If it's a limit error and there's an upgrade URL, show upgrade option.
				if (response.data && response.data.upgrade_url) {
					if (confirm(errorMessage + '\n\n' + jptcAdmin.i18n.upgrade_confirm)) {
						window.open(response.data.upgrade_url, '_blank');
					}
				} else {
					alert(errorMessage);
				}
			}
		})
		.catch(function(error) {
			console.error('Error:', error);
			alert(jptcAdmin.i18n.generate_error);
		});
	}

	/**
	 * Display generated link
	 */
	function displayGeneratedLink(link) {
		const resultSection = document.querySelector('.jump-to-checkout-result-section');
		const linkInput = document.querySelector('.jump-to-checkout-generated-link');

		if (!resultSection || !linkInput) {
			console.error('Result section elements not found');
			return;
		}

		linkInput.value = link;
		resultSection.style.display = 'block';
		
		// Scroll to result.
		resultSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
		
		// Select the link.
		linkInput.select();
	}

	/**
	 * Handle copy link
	 */
	function handleCopyLink() {
		const linkInput = document.querySelector('.jump-to-checkout-generated-link');
		
		if (!linkInput) {
			return;
		}

		linkInput.select();
		linkInput.setSelectionRange(0, 99999); // For mobile devices.

		try {
			document.execCommand('copy');
			showCopySuccess();
		} catch (err) {
			// Fallback to Clipboard API.
			navigator.clipboard.writeText(linkInput.value).then(function() {
				showCopySuccess();
			}).catch(function() {
				alert(jptcAdmin.i18n.copy_error);
			});
		}
	}

	/**
	 * Show copy success message
	 */
	function showCopySuccess() {
		const copyBtn = document.querySelector('.jump-to-checkout-copy-link');
		const originalText = copyBtn.textContent;
		
		copyBtn.textContent = jptcAdmin.i18n.copy_success;
		copyBtn.disabled = true;

		setTimeout(function() {
			copyBtn.textContent = originalText;
			copyBtn.disabled = false;
		}, 2000);
	}

	/**
	 * Escape HTML
	 */
	function escapeHtml(text) {
		const map = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#039;'
		};
		return text.replace(/[&<>"']/g, function(m) {
			return map[m];
		});
	}

	/**
	 * Strip HTML tags
	 */
	function stripHtml(html) {
		const tmp = document.createElement('div');
		tmp.innerHTML = html;
		return tmp.textContent || tmp.innerText || '';
	}
})();

