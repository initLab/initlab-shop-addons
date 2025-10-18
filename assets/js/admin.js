jQuery($ => {
	function openSelectAndWaitForSingleItem() {
		const $select = jQuery(':input.wc-product-search[name="item_id"].enhanced').last();
		const selectApi = $select.data('select2');

		selectApi.on('results:all', e => {
			if (e.data.results.length === 1) {
				const result = e.data.results[0];

				selectApi.trigger('select', {
					data: result,
				});

				requestAnimationFrame(openSelectAndWaitForSingleItem);
			}
		});

		$select.selectWoo('open');
	}

	const search = new URLSearchParams(location.search.substring(1));

	if (search.get('page') === 'wc-orders') {
		switch (search.get('action')) {
			case 'new':
				if ($('#order_line_items').children().size() > 0) {
					return;
				}

				$(':input#order_status.enhanced').val('wc-processing').trigger('change');
				$('.add-order-item').click();
				openSelectAndWaitForSingleItem();
				break;
			case null:
				$('body').on('click', 'a.wc-action-button-pay-cash', e => {
					if (!confirm(initLabShopVars?.txtConfirmPaymentCash)) {
						e.preventDefault();
					}
				});
				break;
		}
	}
});
