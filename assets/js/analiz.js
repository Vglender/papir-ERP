const openModalButtonOrder = document.getElementById('order');
const openModalButtonDemand = document.getElementById('demand');
const openModalButtonPayment = document.getElementById('payment');
const openModalButtonProfit = document.getElementById('profit');
const ModalClose = document.getElementById('closemodal');
const modalcenter = document.querySelector('.bs-example-modal-center');
var openModalTabWeek = document.getElementById('tabweek');
var openModalTabDay = document.getElementById('tabday');
var openModalTabMonth = document.getElementById('tabmonth');
var openModalTabYear = document.getElementById('tabyear');
var ordersum = document.querySelector('[data-plugin="ordersum"]');
var demandsum = document.querySelector('[data-plugin="demandsum"]');
var paidsum = document.querySelector('[data-plugin="paidsum"]');
var profitsum = document.querySelector('[data-plugin="profitsum"]');
var ordercount = document.querySelector('[data-plugin="ordercount"]');


openModalButtonDemand.addEventListener('click', () => {
	OpenModal('today', 'demand');
});
openModalButtonOrder.addEventListener('click', () => {
	OpenModal('today', 'order');
});
openModalButtonPayment.addEventListener('click', () => {
	OpenModal('today', 'payment');
});
openModalButtonProfit.addEventListener('click', () => {
	OpenModal('today', 'profit');
});
openModalTabWeek.addEventListener('click', () => {
	OpenModal('week', GetCurrentTypeModal());
});
openModalTabDay.addEventListener('click', () => {
	OpenModal('today', GetCurrentTypeModal());
});
openModalTabMonth.addEventListener('click', () => {
	OpenModal('month', GetCurrentTypeModal());
});
openModalTabYear.addEventListener('click', () => {
	OpenModal('year', GetCurrentTypeModal());
});


$(modalcenter).on('hidden.bs.modal', function (e) {
	$('.nav-tabs .active').removeClass('active');
	$('.nav-tabs li:first-child').addClass('active');
});

let elements_date = document.querySelectorAll('[data-plugin="MonthYear"]');
elements_date.forEach(function (element) {
	element.textContent = GetMonth();
});

function Updstatistic() {
	$.ajax({
		url: 'https://officetorg.com.ua/webhooks/EndPoints/port.php',
		type: 'POST',
		data: {func: 'plugins', period: 'today'},
		success: function (response) {
			var response_js = JSON.parse(response);
			ordersum.innerHTML = new Intl.NumberFormat("ru").format(response_js['orders']['ordersum']);
			demandsum.innerHTML = new Intl.NumberFormat("ru").format(response_js['demand']);
			paidsum.innerHTML = new Intl.NumberFormat("ru").format(response_js['debet']);
			profitsum.innerHTML = new Intl.NumberFormat("ru").format(response_js['profit']);
		},

	})
};

function CreateReport(period) {
	$.ajax({
		url: 'https://officetorg.com.ua/webhooks/EndPoints/port.php',
		type: 'POST',
		data: {func: 'plugins', period: period},
		success: function (response_report) {
			var response_js_report = JSON.parse(response_report);
//			console.log(response_js_report);
			CreateReportTable(response_js_report, 'all');
		},
	})
}

function RemoveTable() {
	var table_old = report_result.querySelector("table");
	if (table_old) {
		$('#report_result').find('table').remove();
		$('#report_result').find('button').remove();
	}
	return;
}

function CreateReportTable(report, type) {

	const report_result = document.getElementById('report_result');

	if (document.getElementById('button_reload') == null) {
		var button_reload = document.createElement('button');
		button_reload.classList.add('btn', 'btn-outline-info', 'waves-effect', 'width-md', 'waves-light');
		button_reload.innerText = 'Оновити';
		button_reload.id = 'button_reload';
		report_result.append(button_reload);
	}
	var button_reload = document.querySelector('#button_reload');
	button_reload.addEventListener('click', function () {
		if (period) {
			RemoveTable();
			CreateReport(period);
		}
		;
	});


	if (document.getElementById('button_close') == null) {
		var button_close = document.createElement('button');
		button_close.classList.add('btn', 'btn-outline-danger', 'waves-effect', 'width-md', 'waves-light');
		button_close.innerText = 'Закрити';
		button_close.id = 'button_close';
		report_result.append(button_close);
	}
	var button_close = document.querySelector('#button_close');
	button_close.addEventListener('click', function () {
		RemoveTable();
	});


	var content;
//	console.log(report_result.childNodes);
	var table_old = report_result.querySelector("table");
	if (table_old) {
		$('#report_result').find('table').remove();
	} else {
		var table = document.createElement('table');
	}
	var part = {
		'order_all': 'Замовлення',
		'demand': 'Відвантаження',
		'payment_all': 'Платежі',
		'profit': 'Прибуток',
		'expenses': 'Видатки'
	};
	var table = document.createElement('table');
	for (var key in part) {
		var content = CreateContent(report, key);
//		console.log(content);
		var table = document.createElement('table');
		//	table.className = 'table  table-striped';
		table.className = 'table m-0 table-colored-bordered table-bordered-blue table table-striped';
		table.style.tableLayout = 'fixed';
		var thead = document.createElement("thead");
		var headerRow = document.createElement("tr");
		var headerCell = document.createElement("th");
		headerCell.appendChild(document.createTextNode(part[key]));
		headerRow.appendChild(headerCell);
		var headerCell = document.createElement("th");
		headerRow.appendChild(headerCell);
		thead.appendChild(headerRow);
		table.appendChild(thead);

		var tbody = document.createElement("tbody");

		for (var key in content) {
			var row = tbody.insertRow();
			var cell = row.insertCell();
			cell.innerHTML = key;
			var cell = row.insertCell();
			cell.innerHTML = content[key];
			tbody.appendChild(row);
		}

		table.appendChild(tbody);
		report_result.append(table);
	}


	return;


}

function OpenModal(period, type) {
	$(modalcenter).modal('show');
	$.ajax({
		url: 'https://officetorg.com.ua/webhooks/EndPoints/port.php',
		type: 'POST',
		data: {func: 'plugins', period: period},
		success: function (response) {
			var response_js = JSON.parse(response);
			var content_modal = CreateContent(response_js, type);
			CreateElementModal('modalcontent', content_modal, type);
		},
	})
}

function GetCurrentTypeModal() {
	var modaltypecurrent = document.getElementById('modalcontent');
	var type = modaltypecurrent.getAttribute('data-type');
	return type;
}

function CreateContent(response_js, type) {
	var content = {};
	console.log(response_js['orders']);
	const profit_arr = {
		'Маржа: ': response_js['margin'],
		'Витрати операційні: ': response_js['SumExpensess'] - response_js['expenses']['Дивиденты'],
		'Прибуток операційний': response_js['profit'] + response_js['expenses']['Дивиденты'],
		'Витрати неопераційні: ': response_js['expenses']['Дивиденты'],
		'Прибуток чистий: ': response_js['profit'],
	};
	const expenses = {
		'Повернення: ': response_js['profit_return'],
		'Списання: ': response_js['loss'],
		'Податки: ': response_js['expenses']['Налоги'],
		'Оренда: ': response_js['expenses']['Аренда'],
		'Soft: ': response_js['expenses']['Soft'],
		'ГСМ: ': response_js['expenses']['ГСМ'],
		'Доставка: ': response_js['expenses']['Доставка'],
		'Реклама: ': response_js['expenses']['Реклама'],
		'Операційні: ': response_js['expenses']['Операционные'],
		'Ремонт: ': response_js['expenses']['Ремонт'],
		'Комісії: ': response_js['expenses']['Комиссии'],
		'Кредити: ': response_js['expenses']['Кредиты'],
		'Заробітна плата: ': response_js['expenses']['Зарплата'],
	};
	const demand_arr = {
		'На суму: ': response_js['demand'],
		'Кількість: ': response_js['counts']['demand'],
		'Повернення: ': response_js['salesreturn'],
	};

	const order_arr = {
		'На суму: ': response_js['orders']['ordersum'],
		'Кількість: ': response_js['orders']['ordercount'],
		'Відміна: ': response_js['orders']['sum_canceled'],
	};

	const order_arr_all = {
		'На суму: ': response_js['orders']['ordersum'],
		'Кількість: ': response_js['orders']['ordercount'],
		'Відміна: ': response_js['orders']['sum_canceled'],
	};
	if (response_js['orders']['salesChannel']) {
		var chanel_arr = response_js['orders']['salesChannel'];
		for (var key in chanel_arr) {
			order_arr_all[key] = chanel_arr[key];
		}
	}

	const payment_arr = {
		'Валовий дохід: ': response_js['debet'],
		'Повернення: ': response_js['pay_return'],
		'Видатки: ': response_js['cashout'] + response_js['paymentout'],
		'Flow: ': response_js['flow'],
	};

	const payment_arr_all = {
		'Готівка: ': response_js['cashin'],
		'Безготівка: ': response_js['paymentin'],
		'Повернення: ': response_js['pay_return'],
		'Валовий дохід: ': response_js['debet'],
		'Видатки: ': response_js['cashout'] + response_js['paymentout'],
		'Flow: ': response_js['flow'],
	};


	if (type === 'demand') {
		var array = demand_arr;
	} else if (type === 'order') {
		var array = order_arr;
	} else if (type === 'payment') {
		var array = payment_arr;
	} else if (type === 'profit') {
		var array = profit_arr;
	} else if (type === 'expenses') {
		var array = expenses;
	} else if (type === 'payment_all') {
		var array = payment_arr_all;
	} else if (type === 'order_all') {
		var array = order_arr_all;
	}
	for (var key in array) {
		if (array[key] != 0 && key != 'Кількість: ') {
			content[key] = new Intl.NumberFormat("ru").format(array[key]) + ' грн';
		} else if (array[key] != 0 && key === 'Кількість: ') {
			content[key] = new Intl.NumberFormat("ru").format(array[key]) + ' шт';
		}
	}

	return content;
}


function CreateElementModal(ElementId, content, type) {
	const ModalContent = document.getElementById(ElementId);
	ModalContent.setAttribute('data-type', type);
	let br = document.createElement('br');
	let text = '';
	ModalContent.innerHTML = '';
	if (Object.keys(content).length === 0) {
		ModalContent.innerHTML = 'Нема даних';
	} else {
		var newDiv = document.createElement('table');
		//   	newDiv.className = 'table m-0 table-colored-bordered table-bordered-blue table table-striped';
		newDiv.className = 'table  table-striped';
		for (var key in content) {
			var row = newDiv.insertRow();
			var cell = row.insertCell();
			cell.innerHTML = key;
			var cell = row.insertCell();
			cell.innerHTML = content[key];
			ModalContent.append(newDiv);
		}
	}
}

function GetMonth() {
	const months = ["січня", "лютого", "березня", "квітня", "травня", "червня",
		"липня", "серпня", "вересня", "жовтня", "листопада", "грудня"];

	const date = new Date();
	const currentMonth = months[date.getMonth()];
	const currentYear = date.getFullYear();
	const currentDay = date.getDate();
	return currentDay + " " + currentMonth + " " + currentYear;
};

window.onload = Updstatistic;
setInterval(Updstatistic, 60000);