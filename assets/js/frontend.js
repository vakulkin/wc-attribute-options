(function($) {
    'use strict';
    
    /**
     * WooCommerce Attribute Options Handler
     */
    class WCAttributeOptionsHandler {
        
        constructor($form) {
            this.$variationsForm = $form;
            this.$optionsContainer = $form.find('.wc-attribute-options-container');
            this.previouslySelectedTitle = null;
            this.uniqueId = Math.random().toString(36).substr(2, 9);
            
            this.bindEvents();
            this.initializePreselected();
        }
        
        /**
         * Bind all event listeners
         */
        bindEvents() {
            // Variation events
            this.$variationsForm.on('found_variation', (event, variation) => {
                this.renderOptions(variation);
            });
            
            this.$variationsForm.on('reset_data', () => {
                this.clearOptions();
            });
            
            this.$variationsForm.on('submit', (e) => {
                return this.addOptionsToCart();
            });
            
            // Option selection events - scoped to this form
            this.$variationsForm.on('change', '.wc-attr-option-radio', (e) => {
                this.updateSwatchSelection();
                this.updatePriceDisplay();
            });
            
            this.$variationsForm.on('click', '.wc-attr-option-swatch', (e) => {
                if (!$(e.target).is('.wc-attr-option-radio')) {
                    this.selectRadio(e.currentTarget);
                }
            });
        }
        
        /**
         * Render attribute options for selected variation
         */
        renderOptions(variationData) {
            if (!variationData?.attribute_options) {
                this.clearOptions();
                return;
            }
            
            const html = this.buildOptionsHTML(variationData.attribute_options);
            this.$optionsContainer.html(html);
            
            // Try to select previously selected option by title, otherwise select first
            let selectedRadio = null;
            
            if (this.previouslySelectedTitle) {
                this.$optionsContainer.find('.wc-attr-option-swatch').each((index, element) => {
                    const title = $(element).find('.wc-attr-option-title').text();
                    if (title === this.previouslySelectedTitle) {
                        selectedRadio = $(element).find('.wc-attr-option-radio');
                        return false; // break loop
                    }
                });
            }
            
            // If no match found or no previous selection, select first option
            if (!selectedRadio || selectedRadio.length === 0) {
                selectedRadio = this.$optionsContainer.find('.wc-attr-option-radio').first();
            }
            
            if (selectedRadio && selectedRadio.length) {
                selectedRadio.prop('checked', true).trigger('change');
            }
        }
        
        /**
         * Build HTML for option swatches
         */
        buildOptionsHTML(attributeOptions) {
            let html = '';
            
            $.each(attributeOptions, (attrName, options) => {
                if (!options?.length) return;
                
                html += '<div class="wc-attr-options-group">';
                
                $.each(options, (index, option) => {
                    html += this.buildSwatchHTML(option, index);
                });
                
                html += '</div>';
            });
            
            return html;
        }
        
        /**
         * Build HTML for single swatch
         */
        buildSwatchHTML(option, index) {
            const price = option.price || '0';
            const priceText = option.price ? ` ${this.formatPrice(option.price)}` : '';
            
            let html = `<div class="wc-attr-option-swatch" data-price="${price}">`;
            
            // Add image if exists
            if (option.image) {
                html += `<div class="wc-attr-option-image">
                    <img src="${option.image}" alt="${option.title}">
                </div>`;
            }
            
            // Add details
            html += `<div class="wc-attr-option-details">
                <div class="wc-attr-option-title">${option.title}</div>`;
            
            if (priceText) {
                html += `<div class="wc-attr-option-price">${priceText}</div>`;
            }
            
            html += `</div>
                <input type="radio" name="wc-attr-option-radio-${this.uniqueId}" class="wc-attr-option-radio" value="${index}">
            </div>`;
            
            return html;
        }
        
        /**
         * Clear all displayed options
         */
        clearOptions() {
            this.$optionsContainer.html('');
            this.removePriceDisplay();
            this.previouslySelectedTitle = null;
        }
        
        /**
         * Update all swatch selection states
         */
        updateSwatchSelection() {
            this.$optionsContainer.find('.wc-attr-option-swatch').removeClass('selected');
            const $checked = this.$optionsContainer.find('.wc-attr-option-radio:checked');
            $checked.closest('.wc-attr-option-swatch').addClass('selected');
            
            // Store selected option title for persistence
            if ($checked.length) {
                const $swatch = $checked.closest('.wc-attr-option-swatch');
                this.previouslySelectedTitle = $swatch.find('.wc-attr-option-title').text();
            }
        }
        
        /**
         * Select radio button when swatch is clicked
         */
        selectRadio(swatch) {
            const $radio = $(swatch).find('.wc-attr-option-radio');
            $radio.prop('checked', true).trigger('change');
        }
        
        /**
         * Update price display with additional options cost
         */
        updatePriceDisplay() {
            const additionalPrice = this.calculateAdditionalPrice();
            
            if (additionalPrice > 0) {
                this.showAdditionalPrice(additionalPrice);
            } else {
                this.removePriceDisplay();
            }
        }
        
        /**
         * Calculate total price of selected option
         */
        calculateAdditionalPrice() {
            const $checked = this.$optionsContainer.find('.wc-attr-option-radio:checked');
            
            if ($checked.length === 0) {
                return 0;
            }
            
            return parseFloat($checked.closest('.wc-attr-option-swatch').data('price')) || 0;
        }
        
        /**
         * Show additional price in variation price display
         */
        showAdditionalPrice(price) {
            const $priceDisplay = this.$variationsForm.find('.woocommerce-variation-price .price');
            const $additionalPrice = this.$variationsForm.find('#wc-attr-additional-price-' + this.uniqueId);
            const formattedPrice = this.formatPrice(price);
            
            if ($additionalPrice.length) {
                $additionalPrice.text(formattedPrice);
            } else if ($priceDisplay.length) {
                $priceDisplay.append(`<span id="wc-attr-additional-price-${this.uniqueId}">${formattedPrice}</span>`);
            }
        }
        
        /**
         * Remove additional price display
         */
        removePriceDisplay() {
            this.$variationsForm.find('#wc-attr-additional-price-' + this.uniqueId).remove();
        }
        
        /**
         * Add selected options to cart data
         */
        addOptionsToCart() {
            const selectedOptions = this.getSelectedOptions();
            
            // Remove existing hidden input from this form
            this.$variationsForm.find('input[name="wc_attr_selected_options"]').remove();
            
            // Require option selection if options are available
            if (this.$optionsContainer.find('.wc-attr-option-radio').length > 0 && selectedOptions.length === 0) {
                alert('Please select a packaging option.');
                return false;
            }
            
            // Add selected options as hidden input
            if (selectedOptions.length > 0) {
                this.$variationsForm.append(
                    `<input type="hidden" name="wc_attr_selected_options" value='${JSON.stringify(selectedOptions)}'>`
                );
            }
            return true;
        }
        
        /**
         * Get selected option data
         */
        getSelectedOptions() {
            const options = [];
            const $checked = this.$optionsContainer.find('.wc-attr-option-radio:checked');
            
            if ($checked.length === 0) {
                return options;
            }
            
            const $swatch = $checked.closest('.wc-attr-option-swatch');
            const $details = $swatch.find('.wc-attr-option-details');
            
            options.push({
                title: $details.find('.wc-attr-option-title').text(),
                price: $swatch.data('price')
            });
            
            return options;
        }
        
        /**
         * Format price with currency symbol
         */
        formatPrice(price) {
            const currency = (typeof woocommerce_params !== 'undefined' && woocommerce_params.currency_symbol) 
                ? woocommerce_params.currency_symbol 
                : '$';
            
            return currency + parseFloat(price).toFixed(2);
        }
        
        /**
         * Initialize with preselected variation if exists
         */
        initializePreselected() {
            const variations = this.$variationsForm.data('product_variations');
            
            if (!variations?.length) return;
            
            const selectedVariation = this.findSelectedVariation(variations);
            
            if (selectedVariation) {
                this.renderOptions(selectedVariation);
            }
        }
        
        /**
         * Find currently selected variation
         */
        findSelectedVariation(variations) {
            const selectedAttrs = this.getSelectedAttributes();
            
            if (!selectedAttrs.isComplete) return null;
            
            return variations.find(variation => {
                return Object.keys(selectedAttrs.attributes).every(attrName => {
                    return variation.attributes[attrName] === selectedAttrs.attributes[attrName];
                });
            });
        }
        
        /**
         * Get currently selected attributes from form
         */
        getSelectedAttributes() {
            const attributes = {};
            let isComplete = true;
            
            this.$variationsForm.find('.variations select').each(function() {
                const value = $(this).val();
                
                if (value) {
                    attributes[$(this).attr('name')] = value;
                } else {
                    isComplete = false;
                }
            });
            
            return { attributes, isComplete };
        }
    }
    
    // Initialize on document ready
    $(document).ready(() => {
        $('form.variations_form').each(function() {
            new WCAttributeOptionsHandler($(this));
        });
    });
    
})(jQuery);
