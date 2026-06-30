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

		// When a product is selected, check for variable product.
		jQuery('.jump-to-checkout-product-search').on('select2:select', function(e) {
			const data = e.params.data;
			if (typeof window.jptcGetSelectedVariation !== 'undefined' && data.id) {
				checkIfVariableProduct(data.id, data);
			}
		});

		jQuery('.jump-to-checkout-product-search').on('select2:clear', function() {
			hideVariationSelector();
		});
	}

	/**
	 * Initialize event listeners
	 */
	function initEventListeners() {
		const addProductBtn = document.querySelector('.jump-to-checkout-add-product');
		const generateLinkBtn = document.querySelector('.jump-to-checkout-generate-link');
		const copyLinkBtn = document.querySelector('.jump-to-checkout-copy-link');

		if (addProductBtn) {
			addProductBtn.addEventListener('click', handleAddProduct);
		}

		if (generateLinkBtn) {
			generateLinkBtn.addEventListener('click', handleGenerateLink);
		}

		if (copyLinkBtn) {
			copyLinkBtn.addEventListener('click', handleCopyLink);
		}

		const expiryRadios = document.querySelectorAll('input[name="jptc_expiry_type"]');
		const expiryHoursInput = document.querySelector('input[name="jptc_expiry_hours"]');
		if (expiryRadios.length > 0 && expiryHoursInput) {
			expiryHoursInput.disabled = true;
			expiryRadios.forEach(function(radio) {
				radio.addEventListener('change', function() {
					expiryHoursInput.disabled = this.value !== 'custom';
				});
			});
		}
	}

	/**
	 * Check if selected product is variable and show variation selector
	 */
	function checkIfVariableProduct(productId, selectData) {
		if (selectData && selectData.variation_id) {
			return;
		}

		hideVariationSelector();

		const url = new URL(jptcAdmin.ajax_url);
		url.searchParams.append('action', 'jptc_get_product_variations');
		url.searchParams.append('product_id', productId);
		url.searchParams.append('nonce', jptcAdmin.nonce);

		fetch(url.toString())
			.then(function(response) {
				return response.json();
			})
			.then(function(response) {
				if (response.success && response.data && response.data.attributes && Object.keys(response.data.attributes).length > 0) {
					showVariationSelector(productId, response.data);
				}
			})
			.catch(function(error) {
				console.error('Error checking product:', error);
			});
	}

	/**
	 * Show variation selector
	 */
	function showVariationSelector(productId, data) {
		let selector = document.querySelector('.jptc-variation-selector');

		if (!selector) {
			selector = createVariationSelector(productId, data);
			const productRow = document.querySelector('.jump-to-checkout-product-row');
			if (productRow && productRow.parentNode) {
				productRow.parentNode.insertBefore(selector, productRow.nextSibling);
			}
		} else {
			selector.setAttribute('data-product-id', productId);
			updateVariationSelector(selector, data);
		}

		selector.dataset.productId = productId;
		selector.dataset.variationData = JSON.stringify(data);
		selector.style.display = 'block';
	}

	/**
	 * Create variation selector HTML
	 */
	function createVariationSelector(productId, data) {
		const container = document.createElement('div');
		container.className = 'jptc-variation-selector';
		container.setAttribute('data-product-id', productId);

		let html = '<h4>' + escapeHtml(jptcAdmin.i18n.select_variation || 'Select Variation') + '</h4>';
		html += '<div class="jptc-variation-attributes">';

		for (const attributeName in data.attributes) {
			if (!Object.prototype.hasOwnProperty.call(data.attributes, attributeName)) {
				continue;
			}
			const options = data.attributes[attributeName];
			const attributeSlug = attributeName.replace('attribute_', '').replace('pa_', '');
			html += '<div class="jptc-variation-attribute">';
			html += '<label>';
			html += escapeHtml(attributeName.replace('attribute_', '').replace('pa_', '').replace(/_/g, ' ')) + ': ';
			html += '<select name="jptc_variation_' + attributeSlug + '" class="jptc-variation-select" data-attribute="' + escapeHtml(attributeName) + '">';
			html += '<option value="">' + escapeHtml(jptcAdmin.i18n.choose_option || 'Choose an option') + '</option>';
			for (let i = 0; i < options.length; i++) {
				html += '<option value="' + escapeHtml(options[i]) + '">' + escapeHtml(options[i]) + '</option>';
			}
			html += '</select></label></div>';
		}

		html += '</div>';
		html += '<div class="jptc-variation-selected" style="display: none;">';
		html += '<p class="description">' + escapeHtml(jptcAdmin.i18n.selected_variation || 'Selected variation:') + ' ';
		html += '<span class="jptc-selected-variation-name"></span></p>';
		html += '<input type="hidden" class="jptc-selected-variation-id" value="" />';
		html += '</div>';

		container.innerHTML = html;

		container.addEventListener('change', function(e) {
			if (e.target.classList.contains('jptc-variation-select')) {
				handleVariationAttributeChange(container);
			}
		});

		return container;
	}

	/**
	 * Update variation selector with new data
	 */
	function updateVariationSelector(selector, data) {
		const attributesContainer = selector.querySelector('.jptc-variation-attributes');
		if (!attributesContainer) {
			return;
		}

		attributesContainer.innerHTML = '';

		for (const attributeName in data.attributes) {
			if (!Object.prototype.hasOwnProperty.call(data.attributes, attributeName)) {
				continue;
			}
			const options = data.attributes[attributeName];
			const attributeSlug = attributeName.replace('attribute_', '').replace('pa_', '');
			const div = document.createElement('div');
			div.className = 'jptc-variation-attribute';
			div.innerHTML = '<label>' +
				escapeHtml(attributeName.replace('attribute_', '').replace('pa_', '').replace(/_/g, ' ')) + ': ' +
				'<select name="jptc_variation_' + attributeSlug + '" class="jptc-variation-select" data-attribute="' + escapeHtml(attributeName) + '">' +
				'<option value="">' + escapeHtml(jptcAdmin.i18n.choose_option || 'Choose an option') + '</option>' +
				options.map(function(opt) {
					return '<option value="' + escapeHtml(opt) + '">' + escapeHtml(opt) + '</option>';
				}).join('') +
				'</select></label>';
			attributesContainer.appendChild(div);
		}
	}

	/**
	 * Handle variation attribute change
	 */
	function handleVariationAttributeChange(container) {
		const variationDataStr = container.dataset.variationData;
		if (!variationDataStr) {
			return;
		}

		const variationData = JSON.parse(variationDataStr);
		const selectedAttributes = {};
		const selects = container.querySelectorAll('.jptc-variation-select');
		let allSelected = true;

		selects.forEach(function(select) {
			const attributeName = select.getAttribute('data-attribute');
			const value = select.value;
			if (value) {
				selectedAttributes[attributeName] = value;
			} else {
				allSelected = false;
			}
		});

		if (allSelected && variationData.variations) {
			const matchingVariation = variationData.variations.find(function(variation) {
				for (const attrName in selectedAttributes) {
					const normalizedKey = attrName.startsWith('attribute_') ? attrName : 'attribute_' + attrName;
					const variationValue = Object.prototype.hasOwnProperty.call(variation.attributes, normalizedKey)
						? variation.attributes[normalizedKey]
						: variation.attributes[attrName];
					if (variationValue !== selectedAttributes[attrName]) {
						return false;
					}
				}
				return true;
			});

			if (matchingVariation) {
				showSelectedVariation(container, matchingVariation);
			} else {
				hideSelectedVariation(container);
			}
		} else {
			hideSelectedVariation(container);
		}
	}

	/**
	 * Show selected variation
	 */
	function showSelectedVariation(container, variation) {
		const selectedDiv = container.querySelector('.jptc-variation-selected');
		const nameSpan = container.querySelector('.jptc-selected-variation-name');
		const idInput = container.querySelector('.jptc-selected-variation-id');

		if (selectedDiv && nameSpan && idInput) {
			const attributeNames = Object.values(variation.attributes);
			nameSpan.textContent = attributeNames.join(' - ');
			idInput.value = variation.variation_id;
			selectedDiv.style.display = 'block';
		}
	}

	/**
	 * Hide selected variation
	 */
	function hideSelectedVariation(container) {
		if (!container) {
			return;
		}
		const selectedDiv = container.querySelector('.jptc-variation-selected');
		if (selectedDiv) {
			selectedDiv.style.display = 'none';
		}
	}

	/**
	 * Hide variation selector
	 */
	function hideVariationSelector() {
		const selector = document.querySelector('.jptc-variation-selector');
		if (selector) {
			selector.style.display = 'none';
		}
	}

	/**
	 * Get selected variation data from the visible selector
	 */
	function getSelectedVariationData() {
		const selector = document.querySelector('.jptc-variation-selector');
		if (!selector || selector.style.display === 'none') {
			return null;
		}

		const idInput = selector.querySelector('.jptc-selected-variation-id');
		if (!idInput || !idInput.value) {
			return null;
		}

		const variationId = parseInt(idInput.value);
		if (!variationId) {
			return null;
		}

		const productId = selector.getAttribute('data-product-id');
		const variation = {};
		selector.querySelectorAll('.jptc-variation-select').forEach(function(select) {
			const attributeName = select.getAttribute('data-attribute');
			if (select.value) {
				variation[attributeName] = select.value;
			}
		});

		return {
			product_id: productId,
			variation_id: variationId,
			variation: variation
		};
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

		const quantity = parseInt(quantityInput.value) || 1;

		// Check if a variation is selected in the variation selector.
		const variationData = getSelectedVariationData();
		if (variationData) {
			const variationIdInput = document.querySelector('.jptc-selected-variation-id');
			const variationNameSpan = document.querySelector('.jptc-selected-variation-name');
			const productName = variationNameSpan ? variationNameSpan.textContent : 'Variation';

			selectedProducts.push({
				product_id: variationData.product_id,
				variation_id: variationData.variation_id,
				variation: variationData.variation,
				name: productName,
				quantity: quantity
			});

			renderSelectedProducts();
			hideVariationSelector();
			jQuery('.jump-to-checkout-product-search').val(null).trigger('change');
			quantityInput.value = 1;
			return;
		}

		const selectedOption = select.options[select.selectedIndex];

		if (!selectedOption || !selectedOption.value) {
			alert(jptcAdmin.i18n.no_products);
			return;
		}

		// If a variable product parent is selected, wait for variation to be chosen.
		const selectData = jQuery('.jump-to-checkout-product-search').select2('data');
		const currentData = selectData && selectData[0] ? selectData[0] : {};
		if (currentData.is_variable) {
			return;
		}

		const productId = selectedOption.value;
		const productName = stripHtml(selectedOption.text);

		const existingIndex = selectedProducts.findIndex(function(p) {
			return p.product_id === productId && !p.variation_id;
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
			if (linkName) {
				linkName.focus();
			}
			return;
		}

		if (selectedProducts.length === 0) {
			alert(jptcAdmin.i18n.no_products_selected);
			return;
		}

		let expiry = 0;
		const expiryType = document.querySelector('input[name="jptc_expiry_type"]:checked');
		const expiryHours = document.querySelector('input[name="jptc_expiry_hours"]');
		if (expiryType && expiryType.value === 'custom' && expiryHours) {
			expiry = parseInt(expiryHours.value) || 0;
		}

		const couponSelect = document.querySelector('select[name="jptc_coupon_code"]');
		const couponCode = couponSelect ? couponSelect.value : '';

		const data = new FormData();
		data.append('action', 'jptc_generate_link');
		data.append('nonce', jptcAdmin.nonce);
		data.append('name', linkName.value.trim());
		data.append('products', JSON.stringify(selectedProducts));
		data.append('expiry', expiry);
		if (couponCode) {
			data.append('coupon_code', couponCode);
		}

		fetch(jptcAdmin.ajax_url, {
			method: 'POST',
			body: data
		})
		.then(function(response) {
			return response.json();
		})
		.then(function(response) {
			if (response.success) {
				const link = response.data.link;
				if (link) {
					displayGeneratedLink(link);
					linkName.value = '';
					selectedProducts.length = 0;
					renderSelectedProducts();
					if (couponSelect) {
						couponSelect.value = '';
					}
					const expiryNeverRadio = document.querySelector('input[name="jptc_expiry_type"][value="never"]');
					if (expiryNeverRadio) {
						expiryNeverRadio.checked = true;
						if (expiryHours) {
							expiryHours.disabled = true;
						}
					}
				} else {
					alert(jptcAdmin.i18n.no_link_in_response);
				}
			} else {
				const errorMessage = (response.data && response.data.message) ? response.data.message : jptcAdmin.i18n.generate_error;
				alert(errorMessage);
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
			return;
		}

		linkInput.value = link;
		resultSection.style.display = 'block';
		resultSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
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
		linkInput.setSelectionRange(0, 99999);

		try {
			document.execCommand('copy');
			showCopySuccess();
		} catch (err) {
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
		return String(text).replace(/[&<>"']/g, function(m) {
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
