
/* window.addEventListener('load', function() {
    var loggedIn = getCookie('loggedIn');
    if (loggedIn !== "true") {
		 window.location.href = 'https://officetorg.com.ua/papir/page-login.html';				 
    }
}); 
 */
// Функция для получения значения куки по имени
function getCookie(name) {
    var cookieValue = "";
    var cookies = document.cookie.split(';');
    for (var i = 0; i < cookies.length; i++) {
        var cookie = cookies[i].trim();
        if (cookie.indexOf(name + "=") === 0) {
            cookieValue = decodeURIComponent(cookie.substring(name.length + 1));
            break;
        }
    }
    return cookieValue;
}

document.addEventListener("DOMContentLoaded", function () {	
    var urlParams = new URLSearchParams(window.location.search);
    var orderId = urlParams.get('id');
	
	if (orderId) {
		console.log(orderId);
		$('#datatable thead').hide();
		$('#Orderdetalies').show();		
	}else{	
		$('#Orderdetalies').hide();
		var table = $('#demand').DataTable({
			serverSide: true,
			"language": {
				"sEmptyTable": "Нема даних для відображення",
				"sInfo": "Показано з _START_ по _END_ з _TOTAL_ записів. Загальна сума відвантажень: <span id='totalDemands'></span>",
				"sInfoEmpty": "Показано 0 записів",
				"sInfoFiltered": "(відібрано из _MAX_ записів)",
				"sInfoPostFix": "",
				"sInfoThousands": " ",
				"sLengthMenu": "Показати _MENU_ записів на сторінці",
				"sLoadingRecords": "Завантаження...",
				"sProcessing": "Обробка...",
				"sSearch": "Пошук:",
				"sZeroRecords": "Збігів не знайдено",
				"oPaginate": {
					"sFirst": "Перша",
					"sLast": "Остання",
					"sNext": "Наступна",
					"sPrevious": "Попередня"
				},
				 "oAria": {
						"sSortAscending": ": активувати для сортування стовпця за зростанням",
						"sSortDescending": ": активувати для сортування стовпця за спаданням"
				}
			},

			ajax: function (data, callback, settings) {
				var order_column = data.columns[data.order[0].column].data; 
				var order_dir = data.order[0].dir;
				var agent = $('#Agent_name').val();
				var name_order = $('#name-order-searh').val();
//				console.log(agent);
				var selectedEmployeeId = $('#employeeSelect').find(':selected').data('employee-id');
					$.ajax({
						url: "https://officetorg.com.ua/webhooks/EndPoints/demands.php",
						type: "GET",
						dataType: "json",
						data: {
							start: data.start, 
							length: data.length, 
							draw: data.draw, 
							order_column: order_column,
							order_dir: order_dir,
							agent : agent,
							name_order : name_order,
							id_owner : selectedEmployeeId
						},
				 
						success: function (response) {
							callback(response);
							$('#totalDemands').text(response.totalDemands);
/* 							table.ajax.reload(); */
							$.contextMenu({
								selector: '.context-menu-trigger',
								trigger: 'left',
								items: {
									edit: { name: 'Редагувати', icon: 'edit' },
									delete: { name: 'Видалити', 
									icon: 'delete', 
										callback: function (key) {
											Swal.fire('Ви дійсно бажаєте видалити замовлення?', 'Підтвердіть вибор', 'question')
											.then((result) => {
												if (result.isConfirmed) {
													var table = $('#datatable').DataTable();
													var $trigger = $(this);
													var rowData = table.row($trigger.closest('tr')).data();
													var id_order = rowData[11];
													console.log(id_order);
													$.ajax({
														url: "https://officetorg.com.ua/webhooks/EndPoints/order.php",
														type: "GET",
														dataType: "json",
														data: {
															id_order: id_order,
															method: "delete"
														},
														success: function (response_ord) {
															console.log(response_ord);
															if (response_ord.status === true) {
																Swal.fire('Замовлення успішно видалено', 'Дякую', 'success');
															} else if(response_ord.errors) {
																Swal.fire('Замовлення не видалено', response_ord.errors, 'error');
															} else {
																Swal.fire('Замовлення не видалено', 'Невідома помилка', 'error');
															}
															table.ajax.reload();														
														},
														error: function (xhr, status, error) {
														console.error(xhr, status, error);
														}
													});
												}else{
													wal.fire('Операція скасована', '', 'info');
												}
											});
										}									
									},
									copy: {name: "Дублювати", icon: "copy"},
									print: {name: "Відправити рахунок", 
											icon: 'print',
											callback: function (key) {
												var table = $('#datatable').DataTable();
												var $trigger = $(this);
												var rowData = table.row($trigger.closest('tr')).data();
												var id_order = rowData[11];
												console.log(id_order);
												$.ajax({
													url: "https://officetorg.com.ua/webhooks/EndPoints/order.php",
													type: "GET",
													dataType: "json",
													data: {
														id_order: id_order,
														method: "send_invoice"
													},
													success: function (response_ord) {
														console.log(response_ord);
														if (response_ord.status === true) {
															window.open(response_ord.invoice, '_blank');
														} else {
															alert(response_ord.errors);
														}
													},
													error: function (xhr, status, error) {
													console.error(xhr, status, error);
													}
												});
											}
											
											},
									print1: {name: "Відправити сповіщення", 
										icon: 'print',									
										},
									}
								});
						},
						error: function (xhr, status, error) {
							console.error(xhr, status, error);
						}
					});
			},
				columns: [
					{ data: 0 },
					{ data: 1, orderable: true },
					{ data: 2, orderable: true }, 
					{ data: 3, orderable: true }, 
					{ data: 4, orderable: true },
					{ data: 5, orderable: true },
					{ data: 6 },
					{ data: 7 }, 
					{ data: 8, orderable: true },
					{ data: 9},
					{ data: 10 },
					{ data: 11 },
					{ data: 12 },
					{ data:  null, defaultContent: '<button class="dripicons-menu context-menu-trigger" ></button>'}
					],
					   order: [[2, 'desc']],
					fixedColumns: {
					leftColumns: 1 // Закрепляем один столбец слева (последний столбец)
					},

			select: {
				style: 'multi',
				selector: '.select-row'
			},
			dom: 'lBfrtip', // Добавляем элементы управления и поле выбора количества элементов
				lengthMenu: [ [10, 25, 50, 100], [10, 25, 50, 100] ], // Настройка выбора количества элементов на странице
				buttons: [
					{
						text: 'Друк',
						action: function (e, dt, node, config) {
							var selectedRowsData = dt.rows({ selected: true }).data().toArray();
							console.log(selectedRowsData);
						}
					},
					{
						text: 'Рахунок',
						action: function (e, dt, node, config) {
							var selectedRowsData = dt.rows({ selected: true }).data().toArray();
							console.log(selectedRowsData);
						}
					},
					{
						text: '<i class="mdi mdi-refresh"></i>', // Иконка "Reload"
						titleAttr: 'Reload', // Всплывающая подсказка
						action: function (e, dt, node, config) {
						$('#Agent_name').val('');
						$('#name-order-searh').val('');
						$('#employeeSelect').val('');
						table.ajax.reload(); // Перезагрузка данных таблицы
						}
					}
				]

			});	
		}
		
			
 		setInterval(function () {
			table.ajax.reload();
			}, 60 * 1000);
			 
			
			$('#Agent_name').on('input', function () { 
				table.ajax.reload();
			});

			$('#name-order-searh').on('input', function () {
				table.ajax.reload();
			});
			$('#employeeSelect').on('change', function () { 
				table.ajax.reload();
			});

            var selectAllCheckbox = $('#selectAllCheckbox');
			var rowCheckboxes = $('.select-row');
			selectAllCheckbox.on('change', function () {
			rowCheckboxes.prop('checked', this.checked);
			table.rows().deselect();
			if (this.checked) {
				table.rows({ search: 'applied' }).select();
			}
		});
		selectAllCheckbox.on('change', function () {
			rowCheckboxes.prop('checked', this.checked);
			table.rows().deselect();
			if (this.checked) {
				table.rows({ search: 'applied' }).select();
			}
		});	
		rowCheckboxes.on('change', function () {
			var allChecked = rowCheckboxes.length === rowCheckboxes.filter(':checked').length;
			selectAllCheckbox.prop('checked', allChecked);
		});

		
	}); 


	

