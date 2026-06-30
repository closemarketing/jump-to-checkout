(function() {
	'use strict';

	let currentVariableProductId = null;
	let currentVariationData = null;

	document.addEventListener('DOMContentLoaded', function() {
		initVariableProducts();
	});

	/**
	 * Initialize variable products functionality
	 */
	function initVariableProducts() {
		// Listen for product selection in Select2.
		if (typeof jQuery !== 'undefined' && jQuery.fn.select2) {
			jQuery('.jump-to-checkout-product-search').on('select2:select', function(e) {
				const data = e.params.data;
				const productId = data.id;

				// Check if it's a variable product (not a variation).
				checkIfVariableProduct(productId);
			});

			// Listen for product removal.
			jQuery('.jump-to-checkout-product-search').on('select2:clear', function() {
				hideVariationSelector();
			});
		}

		// Listen for variation attribute changes.
		document.addEventListener('change', function(e) {
			if (e.target.classList.contains('jptc-variation-select')) {
				handleVariationAttributeChange();
			}
		});
	}

	/**
	 * Check if selected product is variable
	 */
	function checkIfVariableProduct(productId) {
		if (!productId) {
			return;
		}

		// First check if it's already a variation (from Select2 data).
		const select = document.querySelector('.jump-to-checkout-product-search');
		if (select && select.selectedOptions && select.selectedOptions[0]) {
			const selectedData = jQuery(select).select2('data')[0];
			// If it already has variation_id, it's a variation, not a variable product.
			if (selectedData && selectedData.variation_id) {
				return;
			}
		}

		// Hide previous selector.
		hideVariationSelector();

		// Check via AJAX if product is variable.
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
		currentVariableProductId = productId;
		currentVariationData = data;

		// Find or create variation selector container.
		let selector = document.querySelector('.jptc-variation-selector');
		if (!selector) {
			// Create selector if it doesn't exist.
			selector = createVariationSelector(productId, data);
			const productRow = document.querySelector('.jump-to-checkout-product-row');
			if (productRow && productRow.parentNode) {
				productRow.parentNode.insertBefore(selector, productRow.nextSibling);
			}
		} else {
			// Update existing selector.
			selector.setAttribute('data-product-id', productId);
			updateVariationSelector(selector, data);
		}

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

		// Create select for each attribute.
		for (const attributeName in data.attributes) {
			if (data.attributes.hasOwnProperty(attributeName)) {
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
				html += '</select>';
				html += '</label>';
				html += '</div>';
			}
		}

		html += '</div>';
		html += '<div class="jptc-variation-selected" style="display: none;">';
		html += '<p class="description">';
		html += escapeHtml(jptcAdmin.i18n.selected_variation || 'Selected variation:') + ' ';
		html += '<span class="jptc-selected-variation-name"></span>';
		html += '</p>';
		html += '<input type="hidden" class="jptc-selected-variation-id" value="" />';
		html += '</div>';

		container.innerHTML = html;
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

		// Clear existing selects.
		attributesContainer.innerHTML = '';

		// Create new selects.
		for (const attributeName in data.attributes) {
			if (data.attributes.hasOwnProperty(attributeName)) {
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
					'</select>' +
					'</label>';
				attributesContainer.appendChild(div);
			}
		}
	}

	/**
	 * Handle variation attribute change
	 */
	function handleVariationAttributeChange() {
		if (!currentVariationData || !currentVariableProductId) {
			return;
		}

		// Get all selected attributes.
		const selectedAttributes = {};
		const selects = document.querySelectorAll('.jptc-variation-select');
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

		// Find matching variation.
		if (allSelected && currentVariationData.variations) {
			const matchingVariation = currentVariationData.variations.find(function(variation) {
				let matches = true;
				for (const attrName in selectedAttributes) {
					if (variation.attributes[attrName] !== selectedAttributes[attrName]) {
						matches = false;
						break;
					}
				}
				return matches;
			});

			if (matchingVariation) {
				showSelectedVariation(matchingVariation);
			} else {
				hideSelectedVariation();
			}
		} else {
			hideSelectedVariation();
		}
	}

	/**
	 * Show selected variation
	 */
	function showSelectedVariation(variation) {
		const selectedDiv = document.querySelector('.jptc-variation-selected');
		const nameSpan = document.querySelector('.jptc-selected-variation-name');
		const idInput = document.querySelector('.jptc-selected-variation-id');

		if (selectedDiv && nameSpan && idInput) {
			// Build variation name from attributes.
			const attributeNames = [];
			for (const attrName in variation.attributes) {
				attributeNames.push(variation.attributes[attrName]);
			}
			nameSpan.textContent = attributeNames.join(' - ');
			idInput.value = variation.variation_id;
			selectedDiv.style.display = 'block';
		}
	}

	/**
	 * Hide selected variation
	 */
	function hideSelectedVariation() {
		const selectedDiv = document.querySelector('.jptc-variation-selected');
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
		currentVariableProductId = null;
		currentVariationData = null;
		hideSelectedVariation();
	}

	/**
	 * Get selected variation data for adding to products
	 */
	function getSelectedVariationData() {
		const variationIdInput = document.querySelector('.jptc-selected-variation-id');
		if (!variationIdInput || !variationIdInput.value) {
			return null;
		}

		const variationId = parseInt(variationIdInput.value);
		if (!variationId) {
			return null;
		}

		// Get selected attributes.
		const variation = {};
		const selects = document.querySelectorAll('.jptc-variation-select');
		selects.forEach(function(select) {
			const attributeName = select.getAttribute('data-attribute');
			const value = select.value;
			if (value) {
				variation[attributeName] = value;
			}
		});

		return {
			product_id: currentVariableProductId,
			variation_id: variationId,
			variation: variation
		};
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

	// Export function for use in admin.js.
	window.jptcGetSelectedVariation = getSelectedVariationData;
})();
