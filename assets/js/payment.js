jQuery($ => {
	const defaultOperatorName = 'Оператор 1';
	const defaultOperatorNumber = 1;
	const defaultCashDesk = 1;

	let id = 1;

	const makeRequest = (method, params = null) => ({
	    jsonrpc: '2.0',
	    method,
	    ...(params === null ? {} : {
	        params,
	    }),
	    id: id++,
	});

	async function apiCall(method, params = null) {
	    const requestData = makeRequest(method, params);
	    console.log('Request', requestData);
		const request = cashRegisterRequest(requestData);
	    const timeLabel = `JSON-RPC call: ${method}`;
	    console.time(timeLabel);
	    const responseData = await request;
	    console.timeEnd(timeLabel);
	    console.log('Response', responseData);

	    if (responseData?.jsonrpc !== '2.0') {
	        throw new Error(`Invalid or missing JSON-RPC version in response (expected ${requestData.jsonrpc}, got ${responseData?.jsonrpc})`);
	    }

	    if (responseData?.id !== requestData.id) {
	        throw new Error(`Incorrect or missing JSON-RPC id in response (expected ${requestData.id}, got ${responseData?.id})`);
	    }

	    if (Object.hasOwn(responseData, 'error')) {
	        const error = new Error(responseData.error?.message ?? 'Unknown JSON-RPC error');

	        if (Object.hasOwn(responseData.error, 'code')) {
	            error.code = responseData.error?.code;
	        }

	        if (Object.hasOwn(responseData.error, 'data')) {
	            error.data = responseData.error?.data;
	        }

	        throw error;
	    }

	    return responseData.result;
	}

	function getOperatorInfo(options) {
	    const {
	        operatorName = defaultOperatorName,
	        operatorNumber = defaultOperatorNumber,
	        cashDesk = defaultCashDesk,
	    } = options;

	    return {
	        operatorName,
	        operatorNumber,
	        cashDesk,
	    };
	}

	const ReadItems = async () => await apiCall('ReadItems');
	const PrintAgain = async () => await apiCall('PrintAgain');

	async function PrintReceipt(items, payments, options) {
	    const {
	        operatorName,
	        operatorNumber,
	        cashDesk,
	    } = getOperatorInfo(options);

	    const {
	        usn,
	        invoiceData,
	        subtotalDiscountValue,
	        subtotalDiscountType,
	        stornoData,
	        textAfterPayment = [],
	    } = options;

	    return await apiCall('PrintReceipt', {
	        beginFiscalReceiptInput: {
	            operatorName,
	            operatorNumber,
	            cashDesk,
	            usn,
	        },
	        invoiceData,
	        items,
	        subtotalDiscountValue,
	        subtotalDiscountType,
	        payments,
	        stornoData,
	        textAfterPayment,
	    });
	}

	function redirSuccess() {
		setTimeout(() => {
			location.href = initLabShopOrder.success_url;
		}, 3_000);
	}

	$('#try-again').on('click', async () => {
		$('#print-again').hide();

		try {
			const printAgainResponse = await PrintAgain();
			$('#print-err').hide();
			$('#print-success').show();
			redirSuccess();
		}
		catch (e) {
			$('#print-success').hide();
			$('#print-err').append(e.message).show();
			$('#print-again').show();
		}
	});

	async function printReceipt() {
		if (!window.initLabShopOrder) {
			alert('Error: Order data not found');
			return;
		}

		$('#print-start').show();

		try {
			const receiptResponse = await PrintReceipt(initLabShopOrder.items.flatMap(item => ([{
				type: 'ITEM',
				department: item.department,
				name: item.name,
				vat: 'A',
				price: parseFloat(item.price),
				quantity: item.quantity,
			}, {
				type: 'TEXT',
				text: ` Код: ${item.sku}`,
			}])), [{
				type: `PAYMENT_${initLabShopOrder.payment_method.toUpperCase()}`,
				amount: parseFloat(initLabShopOrder.total),
			}], {
			    textAfterPayment: [{
			        text: `      Поръчка номер ${initLabShopOrder.id}`,
			        type: 'TEXT',
			    }, {
			        text: ' Благодарим Ви за подкрепата!',
			        type: 'TEXT',
			    }],
			});

			if (receiptResponse.printResult === 'SUCCESS') {
				$('#print-err').hide();
				$('#print-success').show();
				redirSuccess();
			}
			else {
				$('#print-err').append(receiptResponse.printResult).show();
				$('#print-again').show();
			}
		}
		catch (e) {
			$('#print-err').append(e.message).show();
			$('#print-again').show();
		}
	}

	$(window).on('load', async () => {
		if (!window.cashRegisterRequest) {
			alert('init Lab Shop Helper extension is not installed!');
			return;
		}

		try {
			const pingResponse = await ReadItems();
			$('#conn-success').show();
			printReceipt();
		}
		catch (e) {
			$('#conn-err').append(e.messages).show();
		}
	});
});
