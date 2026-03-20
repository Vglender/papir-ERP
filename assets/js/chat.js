// Извлечение параметров из URL
        const urlParams = new URLSearchParams(window.location.search);
        const doc = urlParams.get('doc');
        const id = urlParams.get('id');
		
		$(document).ready(function() {
		var agent; // объявите переменную на уровне видимости
		var order;
		});
		
		  // Обработчик изменения шаблона
        $("#templateSelect").change(function() {
            var selectedTemplate = $(this).val();
			var inputName = $("#recipientName").val();
			if (inputName.trim() !== "") {
				var templateText = getInputName(inputName) + "," + "\nдякуємо за ваше замовлення!\n\n";
			}else{
				var templateText = "Дякуємо за ваше замовлення!\n";
			}

            // Генерация текста шаблона в зависимости от выбранного варианта
            switch (selectedTemplate) {
                case "payment":
                    templateText += "5169 3351 0300 6215 (Приват)\n5375 4141 0074 9232 (Моно)\n4149 5100 2660 1957 (Аваль)\n5465 8697 3016 9085 (Отп)\n\n" + "Сума до оплати:\n" + (order ? order.sum.toFixed(2) : "") + " грн";
                    break;
				case "sendinvoice" :
					generateInvoice(order.id);
					 break;
                case "productList":
                    templateText = "Список товарів:\n";
                    if (order && order.positions) {
						order.positions.forEach(function(item) {
							templateText += "- " + item.name + " (" + item.quantity + " шт по " + item.price.toFixed(2) + " грн);" + "\n";
						});
						templateText += "Загальна сума: " + (order ? order.sum.toFixed(2) : "") + " грн";
					}
                    break;
                default:
                    // Добавьте обработку других шаблонов по необходимости
                    break;
            }

            // Вставляем текст шаблона в поле сообщения
            $("#message").val(templateText);
        });
		
		if (doc !== null && id !== null ) {
            // Отправка параметров на сервер с использованием $.ajax
            $.ajax({
                url: 'https://officetorg.com.ua/webhooks/wh/doc_upload.php',
                type: 'GET',
                data: { doc: doc, id: id },
                success: function(response) {
					var result = JSON.parse(response);
					console.log(result)
					order = result.order;
					agent = result.agent;
					console.log(order);
					console.log(agent);
					$("#recipientName").val(agent.name || "");
					$("#recipientPhone").val(agent.phone || "");
					$("#recipientEmail").val(agent.email|| "");
					order.agent_name = agent.name;
					displayClientInfo(agent);
					displayCurrentOrder(order);
					
                },
                error: function(error) {
                    console.error('Error:', error);
                }
            })
        }

		
function displayClientInfo(agent) {

	  // Обновление формы с данными о клиенте
	    var clientInfoTab = document.getElementById("client-info");
	    clientInfoTab.innerHTML = "";
	    var propertiesToDisplay = ["name", "phone","email","legalTitle","code","created"];
	    var propertyNamesMap = {
			name: "Ім'я",
			phone: "Телефон",
			email: "Email",
			legalTitle: "Компанія",
			code: "ЄДРПОУ",
			created: "Створений"
			};
		for (var property in agent) {
			if (propertiesToDisplay.includes(property)) {
				var value = agent[property];
				// Получаем  название свойства из карты
				var ukrPropertyName = propertyNamesMap[property] || property;
				// Создаем элемент и добавляем его во вкладку
				var pElement = document.createElement("p");
				pElement.innerHTML = `${ukrPropertyName}: ${value}`;
				clientInfoTab.appendChild(pElement);
			}
		}
	}
	
function displayCurrentOrder(order) {
		
	    var CurentOrderTab = document.getElementById("current-order");
	    CurentOrderTab.innerHTML = "";
	    var propertiesToDisplay = ["sum","paid_type","payedSum","deliveryPlannedMoment","cargo_metod","city_recipient"];
		var DivNameOrder = document.createElement("div");
		var Spannameorder = document.createElement("span");
		if (order.id){
			var linkorder = "https://officetorg.com.ua/state.php?id=" + order.id + "&";
			var linkElement = document.getElementById("linkorder");
			linkElement.href = linkorder;
			linkElement.style.display = "inline";
		}
		
		DivNameOrder.classList.add('col-md-12');
		DivNameOrder.style.display = "inline-block";
		
		CurentOrderTab.appendChild(DivNameOrder);		
		
		applyStylesToElement_title(Spannameorder);

		Spannameorder.innerHTML = "Замовлення № " + order.name + " від " + order.moment;
		
		DivNameOrder.appendChild(Spannameorder);
		
	    var propertyNamesMap = {
			agent_name: "Замовник",
			sum: "Сума",
			payedSum: "Сплачено",
			paid_type: "Форма оплати",
			deliveryPlannedMoment: "Планова доставка",
			cargo_metod: "Перевізник",
			city_recipient: "Місто",
			};
		for (var property in order) {
			if (propertiesToDisplay.includes(property)) {
				var value = order[property];
				// Получаем  название свойства из карты
				var ukrPropertyName = propertyNamesMap[property] || property;
				// Создаем элемент и добавляем его во вкладку
				var pElement = document.createElement("p");
				pElement.innerHTML = `${ukrPropertyName}: ${value}`;
				CurentOrderTab.appendChild(pElement);
			}
		}
	}
      

function generateInvoice(meta) {

    $.ajax({
       url: '/webhooks/EndPoints/generate_invoice.php',
        type: "POST",
        data: {id: meta},
        success: function(response) {
                $("#invoice").attr("href", response).show();
          
        },
        error: function(jqXHR, textStatus, errorThrown) {
            // Обработка ошибки AJAX-запроса, например, вывод в алерт
            alert("Произошла ошибка: " + textStatus + ", " + errorThrown);
        }
    })
}

function getInputName(fullName) {
    var nameParts = fullName.split(" ");
    return nameParts[0];
}
	
function applyStylesToElement_title(element) {
    element.style.display = 'grid';
    element.style.placeItems = 'center';
    element.style.fontSize = '16px';
    element.style.fontWeight = 'bold';
    element.style.color = 'blue';
	}
