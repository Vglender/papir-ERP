document.addEventListener("DOMContentLoaded", function () {	
	
	var table = $('#warehouse_np').DataTable({
		serverSide: true,
		ajax: function (data, callback, settings) {
			var order_column = data.columns[data.order[0].column].data; // Имя столбца для сортировки
			var order_dir = data.order[0].dir; // Направление сортировки
			var city = $('#City_name').val();
			var warehouse = $('#Warehose_name').val();
			var street = $('#Street_name').val();

				$.ajax({
					url: "https://officetorg.com.ua/webhooks/EndPoints/warehouses.php",
						type: "GET",
						dataType: "json",
						data: {
							start: data.start, // Начальная позиция записей
							length: data.length, // Количество записей на странице
							draw: data.draw, // Номер запроса DataTables
							order_column: order_column, // Имя столбца для сортировки
							order_dir: order_dir,
							city: city,
							warehouse: warehouse,
							street : street
						},
				 
						success: function (response) {
							console.log(response); // Логирование ответа сервера
							callback(response);

						},
						error: function (xhr, status, error) {
							console.error(xhr, status, error);
						}
					});
			},
				columns: [
						{ data: 0, orderable: true },
						{ data: 1, orderable: true }, 
						{ data: 2, orderable: true }, 
						{ data: 3, orderable: true },
						{ data: 4, orderable: true },
						{ data: 5, orderable: true },
						{ data: 6, orderable: true, visible: false }, // Тип улицы
						{ data: 7, orderable: true, visible: false }, // Название улицы
						{ data: 8, orderable: true, visible: false }, // Вулиця REF
						{ data: 9, orderable: true, visible: false }  // Місто REF
					],
					   order: [[0, 'asc']], 

			dom: 'lBfrtip', // Добавляем элементы управления и поле выбора количества элементов
				lengthMenu: [ [10, 25, 50, 100], [10, 25, 50, 100] ], // Настройка выбора количества элементов на странице
				buttons: [	
					{
						text: '<i class="mdi mdi-refresh"></i>', // Иконка "Reload"
						titleAttr: 'Reload', // Всплывающая подсказка
						action: function (e, dt, node, config) {
						$('#City_name').val('');
						$('#Warehose_name').val('');
						$('#Street_name').val('');
						table.ajax.reload(); // Перезагрузка данных таблицы
						}
					}
				]

			});	
		 setInterval(function () {
			table.ajax.reload();
			}, 24 * 60 * 60 * 1000);
			
			
			$('#City_name').on('input', function () { 
				table.ajax.reload();
			});

			$('#Warehose_name').on('input', function () {
				table.ajax.reload();
			});
			
			$('#Street_name').on('input', function () {
				var inputValue = $(this).val();
				if(inputValue){
                    $('#Warehose_name').val(''); 
					table.columns([2, 3, 4, 5]).visible(false, false);
					$("#Street-Name, #Street-Ref, #Street-Type").removeClass("hidden"); 
					table.columns([6, 7, 8]).visible(true, false);
				}else{
					table.columns([6, 7 ,8]).visible(false, false);
					$("#Street-Name, #Street-Ref, #Street-Type").addClass("hidden");
					table.columns([2, 3, 4 , 5 ]).visible(true, false);
				}
				table.draw();
				table.ajax.reload();
				
			});
			
	}); 


	

