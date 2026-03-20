document.addEventListener("DOMContentLoaded", function () {
    var table = $('#Ukrposhta').DataTable({
        serverSide: true,
        fixedColumns: true,
        ajax: function (data, callback, settings) {
            var order_column = data.columns[data.order[0].column].data; // Имя столбца для сортировки
            var order_dir = data.order[0].dir; // Направление сортировки
            var agent = $('#Agent_name').val();
            var shiplist = $('#shiplist').val();
            var barcode = $('#Barcode_number').val();
            var selectedEmployeeId = $('#employeeSelect').find(':selected').data('employee-id');
            var selectedState = $('#stateshipment').find(':selected').data('state-name');

            // // Обработчик для выделения всех строк
            // $('#selectAllCheckbox').on('change', function () {
            // 	const isChecked = $(this).is(':checked'); // Проверяем состояние главного чекбокса
            // 	$('#Ukrposhta').find('input.select-row').prop('checked', isChecked); // Устанавливаем флажки всем чекбоксам на текущей странице
            // });
            //
            // // Снимаем главный чекбокс, если снимается чекбокс в строке
            // $('#Ukrposhta').on('change', 'input.select-row', function () {
            // 	if (!$(this).is(':checked')) {
            // 		$('#selectAllCheckbox').prop('checked', false);
            // 	}
            // });

            // console.log(shiplist);

            $.ajax({
                url: "https://officetorg.com.ua/webhooks/EndPoints/ttn_ukr_reestr.php",
                type: "GET",
                dataType: "json",
                data: {
                    start: data.start, // Начальная позиция записей
                    length: data.length, // Количество записей на странице
                    draw: data.draw, // Номер запроса DataTables
                    order_column: order_column, // Имя столбца для сортировки
                    order_dir: order_dir,
                    agent: agent,
                    shiplist: shiplist,
                    barcode: barcode,
                    id_owner: selectedEmployeeId,
                    state_name: selectedState
                },
                success: function (response) {
                    callback(response);
                    $.contextMenu({
                        selector: '.context-menu-trigger',
                        trigger: 'left',
                        items: {
                            copy: {
                                name: "Додати до реєстру",
                                icon: 'copy',
                                callback: function () {
                                    var table = $('#Ukrposhta').DataTable();
                                    var $trigger = $(this);
                                    var rowData = table.row($trigger.closest('tr')).data();
                                    var linkElement = $('<div>').html(rowData[1]).find('a');
                                    var barcode = linkElement.text().trim();

                                    // AJAX-запрос на сервер
                                    $.ajax({
                                        url: 'https://officetorg.com.ua/ukrpochta/',
                                        type: 'POST',
                                        dataType: 'json',
                                        contentType: 'application/json',
                                        data: JSON.stringify({
                                            action: 'client.add-to_or-create_group',
                                            input: {
                                                barcode: barcode
                                            }
                                        }),
                                        success: function (response) {
                                            console.log("Ответ сервера:", response); // Логируем весь ответ от сервера
                                            if (response.status === 'success') {
                                                let message = `
																<div style="text-align: left;">
																	${response.status ? `<p><strong>Статус:</strong> ${response.status}</p>` : ''}
																	${response.name ? `<p><strong>Название группы:</strong> ${response.name}</p>` : ''}
																	${response.countShipment ? `<p><strong>Количество отправлений:</strong> ${response.countShipment}</p>` : ''}
																	${response.countSeats ? `<p><strong>Количество мест:</strong> ${response.countSeats}</p>` : ''}
																</div>
															`;
                                                // Отображаем сообщение
                                                // Swal.fire({
                                                // title: 'Добавлено в групу!',
                                                // html: message,
                                                // icon: 'success'
                                                // });
                                                $('#Ukrposhta').DataTable().ajax.reload();
                                            } else {

                                                let message = `
																<div style="text-align: left;">
																	${response.status ? `<p><strong>Статус:</strong> ${response.status}</p>` : ''}
																	${response.message ? `<p><strong></strong> ${response.message}</p>` : ''}
																</div>
															`;
                                                Swal.fire({
                                                    title: 'Не добавлено в групу!',
                                                    html: message,
                                                    icon: 'error'
                                                });
                                            }
                                        },
                                        error: function (xhr, status, error) {
                                            console.error("Ошибка:", xhr, status, error);
                                            Swal.fire('Помилка', 'Виникла помилка при запиті', 'error');
                                        }
                                    });
                                }
                            },
                            delete_gr: {
                                name: "Видалити з реєстру",
                                icon: 'delete',
                                callback: function () {
                                    var table = $('#Ukrposhta').DataTable();
                                    var $trigger = $(this);
                                    var rowData = table.row($trigger.closest('tr')).data();
                                    var linkElement = $('<div>').html(rowData[1]).find('a');
                                    var barcode = linkElement.text().trim();
                                    console.log(barcode);
                                    // AJAX-запрос на сервер
                                    $.ajax({
                                        url: 'https://officetorg.com.ua/ukrpochta/',
                                        type: 'POST',
                                        dataType: 'json',
                                        contentType: 'application/json',
                                        data: JSON.stringify({
                                            action: 'client.delete_shipment_from_group',
                                            input: {
                                                barcode: barcode
                                            }
                                        }),
                                        success: function (response) {
                                            // console.log("Ответ сервера:", response); // Логируем весь ответ от сервера
                                            if (response.status === 'success') {
                                                table.ajax.reload();
                                                let message = `
																<div style="text-align: left;">
																	${response.status ? `<p><strong>Статус:</strong> ${response.status}</p>` : ''}
																	${response.DelFromBd ? `<p><strong>Видалення з БД:</strong> ${response.DelFromBd}</p>` : ''}
																	${response.message ? `<p><strong></strong> ${response.message}</p>` : ''}
																</div>
															`;

                                                Swal.fire({
                                                    title: 'Видалено з групи',
                                                    html: message,
                                                    icon: 'success'
                                                });
                                                $('#Ukrposhta').DataTable().ajax.reload();
                                            } else {
                                                // Если нет ссылки или произошла другая ошибка
                                                Swal.fire('Помилка', response.message || 'Не вдалося видалити з групи', 'error');
                                            }
                                        },
                                        error: function (xhr, status, error) {
                                            console.error("Ошибка:", xhr, status, error);
                                            Swal.fire('Помилка', 'Виникла помилка при запиті', 'error');
                                        }
                                    });
                                }
                            },

                            delete: {
                                name: 'Видалити', icon: 'delete',
                                callback: function (key) {
                                    Swal.fire('Ви дійсно бажаєте видалити ттн?', 'Підтвердіть вибор', 'question')
                                        .then((result) => {
                                            if (result.isConfirmed) {
                                                var table = $('#Ukrposhta').DataTable();
                                                var $trigger = $(this);
                                                var rowData = table.row($trigger.closest('tr')).data();
                                                var link_barcode = rowData[1];
                                                var parser = new DOMParser();
                                                var doc = parser.parseFromString(link_barcode, 'text/html');
                                                var linkElement = doc.querySelector('a');
                                                var barcode = linkElement.textContent.trim();

                                                console.log(barcode);

                                                $.ajax({
                                                    url: "https://officetorg.com.ua/webhooks/EndPoints/print_doc.php",
                                                    type: "GET",
                                                    dataType: "json",
                                                    data: {
                                                        barcode: barcode,
                                                        method: "delete"
                                                    },
                                                    success: function (response_delete) {
                                                        console.log(response_delete);
                                                        if (response_delete.status === true) {
                                                            Swal.fire('ТТН успішно видалено', 'Дякую', 'success');
                                                        } else if (response_delete.errors && response_delete.errors.length > 0) {
                                                            var errorString = response_delete.errors.join(', ');
                                                            Swal.fire('ТТН не видалено', errorString, 'error');
                                                        } else {
                                                            Swal.fire('ТТН не видалено', 'Невідома помилка', 'error');
                                                        }
                                                        table.ajax.reload();
                                                    },
                                                    error: function (xhr, status, error) {
                                                        console.error(xhr, status, error);
                                                    }
                                                });
                                            } else {
                                                wal.fire('Операція скасована', '', 'info');
                                            }
                                        });
                                }

                            },

                            print1: {
                                name: "Накладна на сборку",
                                icon: 'print',
                                callback: function (key) {
                                    var table = $('#Ukrposhta').DataTable();
                                    var $trigger = $(this);
                                    var rowData = table.row($trigger.closest('tr')).data();
                                    var id_demand = rowData[11];
                                    $.ajax({
                                        url: 'https://officetorg.com.ua/webhooks/EndPoints/print_doc.php',
                                        type: "GET",
                                        dataType: "json",
                                        data: {
                                            id_demand: id_demand,
                                            type: 1
                                        },
                                        success: function (response) {
                                            console.log(response);
                                            if (response.print_doc) {
                                                var link_demand = response.print_doc;
                                                window.open(link_demand, '_blank');
                                            } else if (response.errors) {
                                                Swal.fire('Помилка', response.errors, 'error');
                                            }
                                        },
                                        error: function (xhr, status, error) {
                                            console.error(xhr); // Вывести информацию об объекте xhr в консоль
                                            console.error(status); // Вывести статус ошибки в консоль
                                            console.error(error);
                                            Swal.fire('Помилка', 'Сталася помилка при відправці запиту на сервер', 'error');
                                        }
                                    });
                                }

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
            {
                data: null, orderable: false, render: function () {
                    return '<input type="checkbox" class="select-row">';
                }
            },
            {data: 1, orderable: true},
            {data: 2, orderable: true},
            {data: 3, orderable: true},
            {data: 4, orderable: true},
            {data: 5, orderable: true},
            {data: 6, orderable: true},
            {data: 7, orderable: true},
            {data: 8, orderable: true},
            {data: 9, orderable: true},
            {data: 10, orderable: true},

            {data: null, defaultContent: '<button class="dripicons-menu context-menu-trigger"></button>'}
        ],
        order: [[3, 'desc']],
        fixedColumns: {
            leftColumns: 1 // Закрепляем один столбец слева (последний столбец)
        },

        dom: 'lBfrtip', // Добавляем элементы управления и поле выбора количества элементов
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]], // Настройка выбора количества элементов на странице
        buttons: [
            {
                text: '<i class="mdi mdi-refresh"> БД </i>', // Иконка "Reload"
                titleAttr: 'Reload', // Всплывающая подсказка
                action: function (e, dt, node, config) {
                    $('#Agent_name').val('');
                    $('#Barcode_number').val('');
                    $('#shiplist').val('');
                    $('#employeeSelect').val('');
                    $('#stateSelect').val('');
                    $('#Ukrposhta').DataTable().ajax.reload(); // Перезагрузка данных таблицы
                }
            },
            {
                text: '<i class="mdi mdi-refresh"></i> АПІ', // Кнопка для обновления статуса ТТН
                titleAttr: 'Оновити',
                action: function () {
                    $.ajax({
                        url: 'https://officetorg.com.ua/ukrpochta/',
                        type: 'POST',
                        dataType: 'json',
                        contentType: 'application/json',
                        data: JSON.stringify({
                            action: 'client.update_status_ttn'  // Укажите действие для обновления статуса
                        }),
                        success: function (response) {
                            if (response.status === 'success') {
                                console.log("Ответ сервера:", response);
                                Swal.fire('Успіх', response.message || 'ТТН оновлено', 'success');
                                table.ajax.reload();
                            } else {
                                console.log("Ответ сервера:", response);
                                Swal.fire('Помилка', response.message || 'Не вдалося оновити ТТН', 'error');
                            }
                        },
                        error: function (xhr, status, error) {
                            console.error("Помилка:", xhr, status, error);
                            Swal.fire('Помилка', 'Виникла помилка при запиті', 'error');
                        }
                    });
                }
            },
            {
                text: '<i class="mdi mdi-refresh"></i> MS', // Кнопка для обновления статуса ТТН
                titleAttr: 'Оновити',
                action: function () {
                    $.ajax({
                        url: 'https://officetorg.com.ua/webhooks/EndPoints/ms_upd_logistika.php',
                        type: 'POST',
                        dataType: 'json',
                        contentType: 'application/json',
                        data: JSON.stringify({
                            action: 'client.ms_update'  // Укажите действие для обновления статуса
                        }),
                        success: function (response) {
                            if (response.status === 'success') {
                                console.log("Ответ сервера:", response);
                                Swal.fire('Успіх', response.message || 'ТТН поєднанно з Мій склад', 'success');
                                table.ajax.reload();
                            } else {
                                console.log("Ответ сервера:", response);
                                Swal.fire('Помилка', response.message || 'Не вдалося оновити Мій склад', 'error');
                            }
                        },
                        error: function (xhr, status, error) {
                            console.error("Помилка:", xhr, status, error);
                            Swal.fire('Помилка', 'Виникла помилка при запиті', 'error');
                        }
                    });
                }
            },
            {
                text: 'ТТН',
                action: function () {
                    let barcodeData = {};
                    const table = $('#Ukrposhta').DataTable(); // Получаем экземпляр DataTables

                    // Получаем данные из отмеченных строк
                    table.rows({page: 'current'}).nodes().to$().find('input.select-row:checked').each(function () {
                        let row = $(this).closest('tr'); // Находим строку
                        let rowData = table.row(row).data(); // Получаем данные строки

                        if (rowData) {
                            console.log(rowData);
                            let cellHtml = rowData[0];
                            let barcode = $(cellHtml).attr('id').replace('checkbox_', ''); // Извлекаем barcode
                            barcodeData[barcode] = {'hideWeight': 0}; // Формируем объект
                        }
                    });

                    // Если нет отмеченных чекбоксов
                    if ($.isEmptyObject(barcodeData)) {
                        Swal.fire('Помилка', 'Оберіть хоча б один рядок для друку', 'warning');
                        return;
                    }

                    $.ajax({
                        url: 'https://officetorg.com.ua/ukrpochta/',
                        type: 'POST',
                        dataType: 'json',
                        contentType: 'application/json',
                        data: JSON.stringify({
                            action: 'client.get_sticker', // Укажите действие для обновления статуса
                            input: {
                                barcode: barcodeData  // Передаем сформированные данные с баркодами
                            }
                        }),
                        success: function (response) {
                            if (response.status === 'success') {
                                console.log("Ответ сервера:", response);
                                if (response.sticker) {
                                    window.open(response.sticker, '_blank');
                                }
                                $('#Ukrposhta').DataTable().ajax.reload();
                            } else {
                                console.log("Ответ сервера:", response);
                                Swal.fire('Помилка', response.message || 'Не вдалося оновити статуси', 'error');
                            }
                        },
                        error: function (xhr, status, error) {
                            console.error("Ошибка:", xhr, status, error);
                            Swal.fire('Помилка', 'Виникла помилка при запиті', 'error');
                        }
                    });
                }
            },
            {
                text: 'Накладні',
                action: async function () {
                    const table = $('#Ukrposhta').DataTable(); // Получаем экземпляр DataTables
                    let errors = []; // Массив для ошибок
                    let selectedRows = [];

                    // Сбор данных из выделенных строк
                    table.rows({ page: 'current' }).nodes().to$().find('input.select-row:checked').each(function () {
                        let row = $(this).closest('tr'); // Получаем строку
                        let rowData = table.row(row).data(); // Получаем данные строки
                        let cellHtml = rowData[0]; // Получаем HTML нулевой колонки
                        let trackingNumber = $(cellHtml).attr('id').replace('checkbox_', ''); // Извлекаем баркод
                        let id_demand = rowData[11]; // Получаем id_demand для запроса

                        selectedRows.push({ trackingNumber, id_demand }); // Сохраняем данные
                    });

                    // Если нет отмеченных чекбоксов
                    if (selectedRows.length === 0) {
                        Swal.fire('Помилка', 'Оберіть хоча б один рядок для друку', 'warning');
                        return;
                    }

                    // Показываем спиннер
                    Swal.fire({
                        title: 'Завантаження накладних...',
                        html: '<div class="swal-spinner"></div>',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        didOpen: () => {
                            // Стили для спиннера
                            $('.swal-spinner').css({
                                'border': '8px solid rgba(0, 0, 0, 0.1)',
                                'border-top': '8px solid #3498db',
                                'border-radius': '50%',
                                'width': '60px',
                                'height': '60px',
                                'animation': 'spin 1s linear infinite',
                                'margin': '20px auto' // Центрирование спиннера
                            });

                            // Добавляем CSS для анимации
                            $('<style>')
                                .prop('type', 'text/css')
                                .html(`
                        @keyframes spin {
                            to { transform: rotate(360deg); }
                        }
                        .swal2-popup {
                            padding: 0; /* Убираем лишние отступы */
                        }
                        .swal2-title {
                            margin: 20px 0; /* Слегка уменьшаем отступы для заголовка */
                        }
                        .swal2-html-container {
                            margin: 0; /* Убираем лишние отступы для контейнера */
                            overflow: hidden; /* Убираем скроллинг */
                        }
                    `)
                                .appendTo('head');
                        }
                    });

                    // Функция для задержки
                    const delay = (ms) => new Promise(resolve => setTimeout(resolve, ms));

                    // Обрабатываем запросы по очереди с задержкой
                    for (const row of selectedRows) {
                        try {
                            const response = await $.ajax({
                                url: 'https://officetorg.com.ua/webhooks/EndPoints/print_doc.php',
                                type: "GET",
                                dataType: "json",
                                data: {
                                    id_demand: row.id_demand,
                                    type: 1
                                }
                            });

                            if (response.print_doc) {
                                window.open(response.print_doc, '_blank'); // Открываем документ
                            } else {
                                errors.push(row.trackingNumber); // Сохраняем баркод с ошибкой
                            }
                        } catch (error) {
                            errors.push(row.trackingNumber); // Сохраняем баркод с ошибкой
                        }

                        // Задержка перед следующим запросом
                        await delay(100); // 0.1 секунды
                    }

                    // Закрываем спиннер
                    Swal.close();

                    // Выводим результат
                    if (errors.length > 0) {
                        Swal.fire('Помилка', `НЕ отримані накладні: ${errors.join(', ')}`, 'error');
                    } else {
                        Swal.fire('Успіх', 'Усі накладні успішно отримані', 'success');
                    }
                }
            },


            {
                text: 'Excel',
                action: function () {
                    let selectedRows = [];
                    const table = $('#Ukrposhta').DataTable(); // Получаем экземпляр DataTables

                    // Сбор данных из выделенных строк
                    table.rows({page: 'current'}).nodes().to$().find('input.select-row:checked').each(function () {
                        let row = $(this).closest('tr'); // Получаем строку
                        let rowData = table.row(row).data(); // Получаем данные строки
                        let cellHtml = rowData[0]; // Получаем HTML нулевой колонки
                        let trackingNumber = $(cellHtml).attr('id').replace('checkbox_', ''); // Извлекаем номер отправления

                        selectedRows.push({
                            'Дата': rowData[4],
                            'ФІО': rowData[5],
                            'Адреса': rowData[6],
                            'ТТН': trackingNumber,
                            'Статус': rowData[10],
                        });
                    });

                    if (selectedRows.length === 0) {
                        Swal.fire('Помилка', 'Оберіть хоча б один рядок для експорту', 'warning');
                        return;
                    }

                    Swal.fire({
                        title: 'Введіть назву файлу',
                        input: 'text',
                        inputPlaceholder: 'Вкажіть ім\'я файлу...',
                        showCancelButton: true,
                        confirmButtonText: 'Експортувати',
                        cancelButtonText: 'Скасувати',
                        inputValidator: (value) => {
                            if (!value) {
                                return 'Назва файлу не може бути порожньою!';
                            }
                        }
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Генерация файла Excel
                            let wb = XLSX.utils.book_new(); // Новый рабочий лист
                            let ws = XLSX.utils.json_to_sheet(selectedRows); // Преобразование данных в формат Excel

                            // Настройка ширины колонок
                            ws['!cols'] = [
                                {wpx: 120},
                                {wpx: 210},
                                {wpx: 150},
                                {wpx: 100},
                                {wpx: 270}
                            ];

                            XLSX.utils.book_append_sheet(wb, ws, 'Sheet1'); // Добавление листа в файл

                            // Используем имя файла, введённое пользователем
                            XLSX.writeFile(wb, result.value + '.xlsx');
                        }
                    });
                }
            },
            {
                text: 'Завантажити',
                action: function () {
                    openUploadModal();
                }
            }
        ],

        drawCallback: function () {
            // Обработчик для "Выбрать всё" при каждой перерисовке
            $('#selectAllCheckbox').on('change', function () {
                const isChecked = $(this).is(':checked'); // Проверяем состояние главного чекбокса
                const table = $('#Ukrposhta').DataTable(); // Получаем экземпляр DataTables

                // Устанавливаем флажки всем чекбоксам на текущей странице
                table.rows({page: 'current'}).nodes().to$().find('input.select-row').prop('checked', isChecked);
            });

            $('#Ukrposhta').on('change', 'input.select-row', function () {
                if (!$(this).is(':checked')) {
                    $('#selectAllCheckbox').prop('checked', false);
                }
            });
        }

    });

    // setInterval(function () {
    // table.ajax.reload();
    // }, 60 * 1000);
    function openUploadModal() {
        Swal.fire({
            title: 'Завантаження файлу',
            html: `
            <div style="text-align: center;">
                <input type="file" id="uploadFile" class="form-control mb-3" style="max-width: 300px; margin: 0 auto;" />
                <button id="uploadBtn" class="btn btn-primary" style="width: 200px;">Завантажити</button>
            </div>
        `,
            showConfirmButton: false,
            allowOutsideClick: false,
            didOpen: () => {
                document.getElementById('uploadBtn').addEventListener('click', () => {
                    const file = document.getElementById('uploadFile').files[0];
                    if (!file) {
                        Swal.showValidationMessage('Оберіть файл для завантаження!');
                        return;
                    }

                    uploadFileToServer(file); // Загружаем файл на сервер
                });
            }
        });
    }


    function uploadFileToServer(file) {
        const formData = new FormData();
        formData.append('file', file);

        // Показываем спиннер во время загрузки файла
        Swal.fire({
            title: 'Завантаження файлу...',
            html: '<div class="swal-spinner"></div>',
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => {
                $('.swal-spinner').css({
                    'border': '8px solid rgba(0, 0, 0, 0.1)',
                    'border-top': '8px solid #3498db',
                    'border-radius': '50%',
                    'width': '60px',
                    'height': '60px',
                    'animation': 'spin 1s linear infinite',
                    'margin': '20px auto'
                });
            }
        });

        $.ajax({
            url: 'https://officetorg.com.ua/webhooks/EndPoints/upload.php',
            type: 'POST',
            processData: false,
            contentType: false,
            data: formData,
            success: function (response) {
                Swal.close(); // Закрываем спиннер
                console.log(response);
                if (response.status === 'success') {
                    const filePath = response.filePath; // Путь к загруженному файлу
                    processFile(filePath); // Переход к следующему этапу
                } else {
                    Swal.fire('Помилка!', 'Не вдалося завантажити файл.', 'error');
                }
            },
            error: function () {
                Swal.close(); // Закрываем спиннер
                Swal.fire('Помилка!', 'Сталася помилка при завантаженні файлу.', 'error');
            }
        });
    }

    function processFile(filePath) {
        Swal.fire({
            title: 'Обробка відправок...',
            html: '<div class="swal-spinner"></div>',
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => {
                $('.swal-spinner').css({
                    'border': '8px solid rgba(0, 0, 0, 0.1)',
                    'border-top': '8px solid #3498db',
                    'border-radius': '50%',
                    'width': '60px',
                    'height': '60px',
                    'animation': 'spin 1s linear infinite',
                    'margin': '20px auto'
                });
            }
        });

        $.ajax({
            url: 'https://officetorg.com.ua/ukrpochta/',
            type: 'POST',
            dataType: 'json',
            contentType: 'application/json',
            data: JSON.stringify({
                action: 'ttn.сreat_ttn_from_exel',
                input: { filePath: filePath }
            }),
            success: function (result) {
                Swal.close(); // Закрываем спиннер
                console.log(result);
                if (result.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Успіх!',
                        html: 'Всі ТТН успішно створено!',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        downloadReport(result.ttn, result.errors); // Скачать отчет только после закрытия
                    });
                } else if (result.status === 'partial_success') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Частковий успіх!',
                        html: result.message,
                        confirmButtonText: 'OK'
                    }).then(() => {
                        downloadReport(result.ttn, result.errors); // Скачать отчет только после закрытия
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Помилка!',
                        html: 'Не вдалося створити ТТН!',
                        confirmButtonText: 'OK'
                    });
                }
            },
            error: function () {
                Swal.close(); // Закрываем спиннер
                Swal.fire({
                    icon: 'error',
                    title: 'Помилка!',
                    html: 'Сталася помилка при обробці файлу.',
                    confirmButtonText: 'OK'
                });
            }
        });
    }



    function getCurrentDateTime() {
        const now = new Date();
        return `${now.getDate()}_${now.getMonth() + 1}_${now.getHours()}`;
    }
    function downloadReport(ttn, errors) {
        const workbook = XLSX.utils.book_new();

        // Создание листа TTN
        const ttnSheetData = ttn.map(item => ({
            Barcode: item.barcode || '',
            Name: item.name || '',
            Phone: item.phone || '',
            DeliveryDate: item.deliveryDate || '',
            DeliveryPrice: item.deliveryPrice || '',
            CalculationDescription: item.calculationDescription || '',
            Status: item.status || '',
            Type: item.type || ''
        }));
        const ttnSheet = XLSX.utils.json_to_sheet(ttnSheetData);
        XLSX.utils.book_append_sheet(workbook, ttnSheet, 'TTN');

        // Создание листа Errors
        const errorSheetData = errors.map(error => ({
            Name: error.name || '',
            Phone: error.phone || '',
            Message: error.message || 'Unknown error'
        }));
        const errorSheet = XLSX.utils.json_to_sheet(errorSheetData);
        XLSX.utils.book_append_sheet(workbook, errorSheet, 'Errors');

        // Сохранение файла
        XLSX.writeFile(workbook, `Report_${getCurrentDateTime()}.xlsx`);
    }



    $('#Agent_name').on('input', function () {
        table.ajax.reload();
    });

    $('#shiplist').on('input', function () {
        table.ajax.reload();
    });

    $('#Barcode_number').on('input', function () {
        table.ajax.reload();
    });
    $('#employeeSelect').on('change', function () {
        table.ajax.reload();
    });
    $('#stateshipment').on('change', function () {
        table.ajax.reload();
    });
});
