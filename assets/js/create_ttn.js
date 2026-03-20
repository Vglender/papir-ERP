var shipmentDataNp = {
	Sender:"",
	ContactSender:"",
	CitySender:"",
	SenderAddress:"",
	Recipient:"",
	CityRecipient:"",
	RecipientAddress:"",
	ContactRecipient:"",
	FirstName:"",
	LastName:"",
	MiddleName:"",
	Phone:"",
	Email:"",
	CounterpartyType:"PrivatePerson",
	CounterpartyProperty:"Recipient",
	ServiceType:"",
	CargoType: "Parcel",
	Cost: "",
	SeatsAmount:"",
	Weight:"",
	VolumeGeneral:"",
    AdditionalInformation: "",
    Description: "Канцелярські товари",
	OptionsSeat: {
		volumetricVolume:"",
		volumetricWidth:"",
		volumetricLength:"",
		volumetricHeight:"",
		weight:""		
	},
	BackwardDeliveryData : {
		PayerType :"",
		CargoType :"",
		Redelivery :""		
	}
	
};
var phoneError = $("#phoneError");



$(document).ready(function() {

    var recipientCityInput = $("#recipientCity");
	var recipientPhoneInput = $("#recipientPhone");
	var recipientFirstNameInput = $("#recipientFirstName");
	var recipientLastNameInput = $("#recipientLastName");
	var recipientMiddleNameInput = $("#recipientMiddleName");
	var sumReturn = $("#sumReturnInput");
	var CostParsel = $("#CostInput");
	var suggestionsLists = {};
	var isFocusEvent = {};
	

	$("#parametrs-form, #sender-form").hide();


	$(".nav-link").click(function() {
	$("#recipient-form, #parametrs-form, #sender-form").hide();
		var tabId = $(this).attr("href");
	$(tabId + "-form").show();
	});
	
	$("#StatusTtn").prop("readonly", true).css("background-color", "#f2f2f2");
	$("#sumReturn").hide();	
    $("#TtnActiv").change(function() {
        var selectedValue = $(this).val();
        if (selectedValue === "створити") {
            $("#TtnNumber").prop("readonly", true).css("background-color", "#f2f2f2");
        } else {
            $("#TtnNumber").prop("readonly", false).css("background-color", "#ffffff");
        }
    });
	
	$("#returnMoney").change(function() {
		var BackwardDelivery = $(this).val();
		if (BackwardDelivery === "Так") {
			$("#sumReturn").show();
		} else {
			$("#sumReturn").hide();
			sumReturn.val('');
			shipmentDataNp.BackwardDeliveryData = {
			PayerType: '',
			CargoType: '',
			Redelivery: ''
		};
		console.log(shipmentDataNp);				
		}
	});
		
    $("#recipientType").change(function() {
        var selectedValue = $(this).val();		
        if (selectedValue === "Підприємство") {
            $("#Counterparty").show();
			$("#typesPaid").show();
        } else{
           $("#Counterparty").hide();
		   $("#typesPaid").hide();
        } 
			
    });
	
	$("#Cargo").change(function() {
        var selectedValue = $(this).val();
		$("#typeUkrposhta").hide();	
        if (selectedValue === "Укрпошта") {
            $("#typeUkrposhta").show();
        } else  {
          $("#typeUkrposhta").hide();
		}
			
    })
	
	$("#TypeCargo").change(function() {
        var selectedValue = $(this).val();		
        if (selectedValue === "W2D") {
			shipmentDataNp.ServiceType = 'WarehouseDoors';
            $("#Adresses").show();
        } else {
			shipmentDataNp.ServiceType = 'WarehouseWarehouse';
            $("#Adresses").hide();
        } 
			
    });
	
	$("#TtnNumber").on("input", function() {
        var ttnNumber = $(this).val();
        if (ttnNumber !== "") {
            $("#TtnActiv").find("option[value='створити']").remove();
        }
    });

//  $("#recipientPhone").mask("(999) 999-99-99");

    $("#recipientPhone").on("blur", function() {
		var phone = $(this).val();
		var suggestionsList = suggestionsLists['recipientPhone'];
		if (checkPhoneNumber(phone) && phone != shipmentDataNp.Phone){	
			Swal.fire({
				title: "Підтвердіть зміни!",
				text: "Ви впевненні, що бажаєте змінити номер телефону?",
				width: 400,
				icon: "warning",
				showCancelButton: true,
				confirmButtonColor: "#3085d6",
				cancelButtonColor: "#d33",
				confirmButtonText: "Так, змінити!",
				cancelButtonText: "Відміна"
					}).then((result) => {
						if(result.isConfirmed){
							shipmentDataNp.Phone  = phone;
							recipientFirstNameInput.val("");
							recipientLastNameInput.val("");
							recipientMiddleNameInput.val("");
							shipmentDataNp.FirstName = shipmentDataNp.LastName = shipmentDataNp.MiddleName = "";
							console.log (shipmentDataNp.Phone);
							suggestionsList.hide();
						}	
		    });			
		}
    }); 

$("#sumReturnInput").on("input", function() {
	var InputsumReturn = $(this).val();
	var Cost = parseFloat(CostParsel.val()) ? parseFloat(CostParsel.val()):0;
	shipmentDataNp.BackwardDeliveryData = {
        PayerType: 'Recipient',
        CargoType: 'Money',
        Redelivery: InputsumReturn
    };
	InputsumReturnFloat = parseFloat(InputsumReturn);
	if(Cost<InputsumReturnFloat){
		CostParsel.val(InputsumReturn);
		shipmentDataNp.Cost = InputsumReturn;
	}
	console.log(shipmentDataNp);
});	

	
 $("#recipientCity").on("input", function() {
        var inputText = $(this).val();
		var fieldId = $(this).attr("id");
        suggestionsLists[fieldId] = $("#citySuggestions");
        // Выполняем AJAX-запрос для получения подсказок
        $.ajax({
            url: "https://officetorg.com.ua/webhooks/EndPoints/search_city.php",
            method: "POST",
            data: { query: inputText },
            dataType: "json",
            success: function(data) {
                // Очищаем предыдущие подсказки
                suggestionsLists[fieldId].empty();

                // Отображаем результаты в выпадающем списке
                if (data.length > 0) {
                    var ul = $("<ul>").addClass("suggestions-list");
                    $.each(data, function(index, city) {
                        var li = $("<li>")
						.text(city.name + ' ' + city.AreaDescription)
						.data("city-data", city);
						ul.append(li);
                    });
                    suggestionsLists[fieldId].append(ul);
					
					ul.on("click", "li", function() {
						var cityData = $(this).data("city-data");
						var name = cityData.name;
						var areaDescription = cityData.AreaDescription;	
                        shipmentDataNp.CityRecipient = cityData.Ref;						
						
					recipientCityInput.val(name + ", " + areaDescription);
					suggestionsLists[fieldId].hide();
				   });
                }
            }
        });
	console.log('input_sity');
	console.log(shipmentDataNp);	
});

 $("#recipientPhone").on("input", function() {
	var inputPhone = $(this).val();
	var fieldId = $(this).attr("id");
    suggestionsLists[fieldId] = $("#recipientPhonesuggestions");
	$.ajax({
        url: "https://officetorg.com.ua/webhooks/EndPoints/search_agent.php",
        method: "POST",
        data: {phone: inputPhone},
        dataType: "json",
        success: function(data) {
        // Очищаем предыдущие подсказки
        suggestionsLists[fieldId].empty();
			// Отображаем результаты в выпадающем списке
			if (data.length > 0) {
				var ul = $("<ul>").addClass("suggestions-list");
				$.each(data, function(index, agent) {
					var li = $("<li>")
						.text(agent.name + ' ' + agent.phone)
						.data("agent-data", agent);
					ul.append(li);
				});
					suggestionsLists[fieldId].append(ul);	
                    ul.on("click", "li", function() {
//						clearRecipientData(); 
						var agentData = $(this).data("agent-data");
						var name = agentData.name;
						var phone = agentData.phone;
					    shipmentDataNp.Email = agentData.email;
						recipientPhoneInput.val(phone);
						if (checkPhoneNumber(phone)){
							console.log ('on.input');
							shipmentDataNp.Phone = phone ;
							console.log(shipmentDataNp.Phone);
						}
						var parts = name.split(/\s+/);
						shipmentDataNp.FirstName = parts[0];
						shipmentDataNp.LastName = (parts[1]) ? parts[1]: "Фамілія";
						shipmentDataNp.MiddleName = (parts[2]) ? parts[2]: "Невідомо";
						recipientFirstNameInput.val(shipmentDataNp.FirstName);
						recipientLastNameInput.val(shipmentDataNp.LastName);
						recipientMiddleNameInput.val(shipmentDataNp.MiddleName);
						suggestionsLists[fieldId].hide();
				   });					
			}
		}
    });
		
});	 
     
    
        // Общий обработчик для полей ввода
$(".recipient-input").on("focus", function() {
		var fieldId = $(this).attr("id");
		isFocusEvent[fieldId] = true;
		// Закрываем другие списки
		for (var otherFieldId in suggestionsLists) {
			if (otherFieldId !== fieldId) {
            suggestionsLists[otherFieldId].hide();
			isFocusEvent[otherFieldId] = false;
			}
		}	
			suggestionsLists[fieldId] = suggestionsLists[fieldId] || $("#" + $(this).data("suggestions-list"));
			suggestionsLists[fieldId].show();

	});

	for (var fieldId in suggestionsLists) {
        suggestionsLists[fieldId].on("mouseenter", "li", function() {
            $(this).addClass("active").siblings().removeClass("active");
        });

        suggestionsLists[fieldId].on("mouseleave", "li", function() {
            $(this).removeClass("active");
        });
    }
	

// Обработчик для закрытия списка при клике вне области списка
	$(".recipient-input").on("click", function() {
		var fieldId = $(this).attr("id");
		var suggestionsList = suggestionsLists[fieldId];	
		if (!isFocusEvent[fieldId]) {
            if (suggestionsList.is(":visible")) {
                suggestionsList.hide();
            } else {
                suggestionsList.show();
            }
        } 
        isFocusEvent[fieldId] = false;		
	}); 
	
});
   
	function checkPhoneNumber(phoneNumber){
        // Регулярное выражение для проверки номера телефона
        var phonePattern = /^(\+38|38)?\s?\(?\d{3}\)?\s?\d{3}(-|\s)?\d{2}(-|\s)?\d{2}$/;
        
        if (!phonePattern.test(phoneNumber)) {
            phoneError.text("Невірний номер!").addClass("error");
			return 0;
        } else {
            phoneError.text("").removeClass("error");
			return 1;
        }
	}
	
    function Fire (textFire,confirmButtonTex){
		return Swal.fire({
				title: "Підтвердіть зміни!",
				text: textFire,
				width: 400,
				icon: "warning",
				showCancelButton: true,
				confirmButtonColor: "#3085d6",
				cancelButtonColor: "#d33",
				confirmButtonText: confirmButtonTex,
				cancelButtonText: "Відміна"
					}).then((result) => {
						return result.isConfirmed;	
		    });
		
	}
	