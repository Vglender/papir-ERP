document.addEventListener("DOMContentLoaded", function () {
    var table = $('#Ukrposhta_shiplist').DataTable({
        serverSide: true,
        fixedColumns: true,
        ajax: function (data, callback, settings) {
            var order_column = data.columns[data.order[0].column].data; // Имя столбца для сортировки
            var order_dir = data.order[0].dir; // Направление сортировки


            $.ajax({
                url: "https://officetorg.com.ua/webhooks/EndPoints/shiplist_ukr.php",
                type: "GET",
                dataType: "json",
                data: {
                    start: data.start, // Начальная позиция записей
                    length: data.length, // Количество записей на странице
                    draw: data.draw, // Номер запроса DataTables
                    order_column: order_column,
                    order_dir: order_dir

                },
                success: function (response) {
                    callback(response);
                    $.contextMenu({
                        selector: '.context-menu-trigger',
                        trigger: 'left',
                        items: {
                            print: {
                                name: "Друк реєстру",
                                icon: 'print',
                                callback: function () {
                                    var table = $('#Ukrposhta_shiplist').DataTable();
                                    var $trigger = $(this);
                                    var rowData = table.row($trigger.closest('tr')).data();

                                    // Получаем значение `uuid_group` из 8-го столбца таблицы
                                    var uuid_group = rowData[8]; // Предполагается, что столбец 8 содержит нужное значение
                                    console.log(uuid_group);

                                    // AJAX-запрос на сервер
                                    $.ajax({
                                        url: 'https://officetorg.com.ua/ukrpochta/',
                                        type: 'POST',
                                        dataType: 'json',
                                        contentType: 'application/json',
                                        data: JSON.stringify({
                                            action: 'client.get_group_shipment_form',
                                            input: {
                                                uuid_group: uuid_group
                                            }
                                        }),
                                        success: function (response) {
                                            console.log("Ответ сервера:", response); // Логируем весь ответ от сервера
                                            if (response.status === 'success' && response.pdf_link && response.pdf_link.startsWith('http')) {

                                                window.open(response.pdf_link, '_blank'); // Открываем PDF в новом окне
                                                console.log("Ответ сервера:", response);
                                            } else {
                                                // Если нет ссылки или произошла другая ошибка
                                                Swal.fire('Помилка', response.message || 'Не вдалося отримати посилання на документ', 'error');
                                            }
                                        },
                                        error: function (xhr, status, error) {
                                            console.error("Ошибка:", xhr, status, error);
                                            Swal.fire('Помилка', 'Виникла помилка при запиті', 'error');
                                        }
                                    });
                                }
                            }
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
            { data: 6, orderable: true },
            { data: 7, orderable: true },
            { data: 8, orderable: true },
            { data: 9, orderable: true },

            {
                data: null, // Используем data: null для последней колонки
                orderable: false,
                render: function() {
                    return '<button class="dripicons-menu context-menu-trigger"></button>';
                }
            }
        ],
        order: [[3, 'desc']],
        fixedColumns: {
            leftColumns: 1 // Закрепляем один столбец слева (последний столбец)
        },

        createdRow: function (row, data, dataIndex) {
            // Предполагается, что статус "Закрито" находится в 5-м столбце
            const closedStatus = data[5];

            if (closedStatus == 1) { // Закрытые реестры
                $(row).css('background-color', 'rgba(128, 128, 128, 0.2)');
            } else { // Открытые реестры
                $(row).css('background-color', 'rgba(144, 238, 144, 0.3)');
            }
        },

        dom: 'lBfrtip', // Добавляем элементы управления и поле выбора количества элементов
        lengthMenu: [ [10, 25, 50, 100], [10, 25, 50, 100] ], // Настройка выбора количества элементов на странице
        buttons: [
            {
                text: '<i class="mdi mdi-refresh"> БД </i>', // Иконка "Reload"
                titleAttr: 'Reload', // Всплывающая подсказка
                action: function (e, dt, node, config) {

                    $('#Ukrposhta_shiplist').DataTable().ajax.reload(); // Перезагрузка данных таблицы
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
                            action: 'client.update_shipment_groups'  // Укажите действие для обновления статуса
                        }),
                        success: function (response) {
                            let allSuccessUpd = true;
                            let errorMessagesUpd = [];

                            // Проходимся по каждому ключу в объекте ответа
                            Object.keys(response).forEach(uuid => {
                                let item = response[uuid];
                                // Проверяем статус каждого объекта
                                if (item.status !== 'success') {
                                    allSuccessUpd = false;
                                    errorMessagesUpd.push(item.message || `Помилка оновлення даних`);
                                }
                            });

                            // Проверяем, все ли операции прошли успешно
                            if (allSuccessUpd) {
                                Swal.fire('Успіх', 'Дані оновлено', 'success');
                                $('#Ukrposhta_shiplist').DataTable().ajax.reload();  // Перезагрузка таблицы
                            } else {
                                Swal.fire('Помилка', errorMessagesUpd.join('<br>'), 'error');
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
                text: 'Закрити реєстр',
                action: function () {

                    let selectedUuidArray = [];

                    // Проходимся по всем отмеченным строкам и собираем значения uuid_group из 8-й колонки
                    $('#Ukrposhta_shiplist').find('input.select-row:checked').each(function () {
                        let checkboxId = $(this).attr('id');  // Получаем ID чекбокса
                        let uuid  = checkboxId.replace('checkbox_', '');  // Извлекаем barcode из ID
                        if (uuid) {
                            selectedUuidArray.push(uuid);
                        }
                    });

                    if (selectedUuidArray.length === 0) {
                        Swal.fire('Помилка', 'Оберіть хоча б один рядок для закриття', 'warning');
                        return;
                    }

                    // Отправляем данные на сервер
                    $.ajax({
                        url: 'https://officetorg.com.ua/ukrpochta/',
                        type: 'POST',
                        dataType: 'json',
                        contentType: 'application/json',
                        data: JSON.stringify({
                            action: 'client.close_ukrposhta_registry',  // Укажите нужное действие
                            input: {
                                uuid_groups: selectedUuidArray  // Передаем сформированные данные с uuid группами
                            }
                        }),
                        success: function (response) {
                            let allSuccess = true;
                            let errorMessages = [];

                            // Проходимся по каждому ключу в объекте ответа
                            Object.keys(response).forEach(uuid => {
                                let item = response[uuid];
                                // console.log(`UUID: ${uuid}, Status: ${item.status}, Message: ${item.message}`);

                                // Проверяем статус каждого объекта
                                if (item.status !== 'success') {
                                    allSuccess = false;
                                    errorMessages.push(item.message || `Помилка закриття реєстру для ${uuid}`);
                                }
                            });

                            // Проверяем, все ли операции прошли успешно
                            if (allSuccess) {
                                Swal.fire('Успіх', 'Реєстри успішно закрито', 'success');
                                $('#Ukrposhta_shiplist').DataTable().ajax.reload();  // Перезагрузка таблицы
                            } else {
                                Swal.fire('Помилка', errorMessages.join('<br>'), 'error');
                            }
                        },
                        error: function (xhr, status, error) {
                            console.error("Ошибка:", xhr, status, error);
                            Swal.fire('Помилка', 'Виникла помилка при запиті', 'error');
                        }
                    });
                }
            }
        ]
    });

    // setInterval(function () {
    // table.ajax.reload();
    // }, 60 * 1000);


    $('#Agent_name').on('input', function () {
        table.ajax.reload();
    });

    $('#Barcode_number').on('input', function () {
        table.ajax.reload();
    });
    $('#employeeSelect').on('change', function () {
        table.ajax.reload();
    });
    $('#stateSelect').on('change', function () {
        table.ajax.reload();
    });
});
