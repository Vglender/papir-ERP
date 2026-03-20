
document.addEventListener('DOMContentLoaded', function() {

var registrationForm = document.querySelector('.form-horizontal');
var usernameInput = document.getElementById('username');
var usernamelable = document.getElementById('lable-name');
var codeSms = document.getElementById('code-sms');
var codeSmslable = document.getElementById('code-sms-lable');
var passwordInput = document.getElementById('password-input');
var passwordInputlable = document.getElementById('password-input-label');
var savePasswordButton = document.getElementById('savePasswordButton');
var phoneInput = document.getElementById('phone');
var getSmsCodeButton = document.getElementById('getSmsCodeButton');
var sendCodeButton = document.getElementById('sendCodeButton');
var sendCode = document.getElementById('sendCode');

var codeError = document.getElementById("codeError");
var phoneError = document.getElementById("phoneError");
var closePhoneError = document.getElementById("closePhoneError");
var closeCodeError = document.getElementById("closeCodeError");
var codeErrorMessageElement = document.getElementById("codeErrorMessage");
var sms ='';

codeSms.setAttribute('disabled', 'disabled');
usernameInput.setAttribute('disabled', 'disabled');
passwordInput.setAttribute('disabled', 'disabled');
usernamelable.style.display = 'none';
usernameInput.style.display = 'none';
sendCode.style.display = 'none';

passwordInputlable.style.display = 'none';
passwordInput.style.display = 'none';
savePasswordButton.style.display = 'none';

usernameInput.setAttribute('required', null);
codeSms.setAttribute('required', null);
passwordInput.setAttribute('required', null);

$("#phone").mask("+38 (999) 999-99-99");

registrationForm.addEventListener('submit', function(event) {
    event.preventDefault(); // Предотвращаем отправку формы по умолчанию
	
	var submitButton = event.submitter;
	
	if (submitButton.id === 'getSmsCodeButton') {
		console.log('Кнопка "Отримати код" была нажат');
		var phone = phoneInput.value;
		phone = phone.replace(/\D/g, '');
		if(checkPhoneNumber(phone)){
			console.log(phone);
			$.ajax({
				url: "https://officetorg.com.ua/webhooks/EndPoints/registration.php",
				type: "GET",
				dataType: "json",
				data: {
					phone: phone,
					method: "search"
				},
				success: function (response_search) {
					console.log(response_search);
					if (response_search.status === true) {
						var firstName = response_search.data.firstName;
						var lastName = response_search.data.lastName;
						sms = response_search.messeg;
						usernameInput.value = firstName + '  ' + lastName ;
						usernamelable.style.display = 'block';						
						usernameInput.style.display = 'block';
						sendCode.style.display = 'block' ;
						codeSms.removeAttribute('disabled');
						codeSms.setAttribute('required', 'required');
						getSmsCodeButton.style.display = 'none';
						phoneInput.setAttribute('disabled', 'disabled');
						
					} else if(response_search.errors) {
																
					} else {
																
					}
																													
				},
				error: function (xhr, status, error) {
						console.error(xhr, status, error);
				}
			});
		}else{
			phoneInput.value ='';
			phoneError.style.display = "block";
		}
		
    } else if (submitButton.id === 'savePasswordButton') {
		console.log('Кнопка "Зберегти" была нажата');
    } else if(submitButton.id === 'sendCodeButton'){
		console.log('Кнопка "код з смс" была нажата');
		var code = codeSms.value;
		console.log(code);
		if(!isFourDigitCode(code)){
			showError('Код повинен містити 4 цифри!');
		}
		if(!sms){
			showError('Не отримано кода за с сервера!');
		}else if(sms != code){
			showError('Введено невірний код з смс!');
			codeSms.value = '';
		}else{
			console.log('Коди співпали!!');	
			sendCode.style.display = 'none' ;
			codeSms.setAttribute('required', null);
			usernameInput.removeAttribute('disabled');
			passwordInput.removeAttribute('disabled');
			passwordInputlable.style.display = 'block';
			passwordInput.style.display = 'block';
			savePasswordButton.style.display = 'block';
			passwordInput.setAttribute('required', 'required');
			
		}		
	}

});

closePhoneError.addEventListener("click", HidephoneError);
closeCodeError.addEventListener("click", HidecodeError);

function checkPhoneNumber(phoneNumber) {
    var operatorCode = phoneNumber.substr(2, 3);
    var validOperatorCodes = ["050", "066", "095", "099", "063", "073", "093", "067", "068", "096", "097", "098", "091", "092", "020", "089", "094", "039"];
	return validOperatorCodes.includes(operatorCode);
}
	
function isFourDigitCode(code) {
    var codePattern = /^\d{4}$/;
	return codePattern.test(code);	
}	

function HidephoneError() {
    phoneError.style.display = "none";
}	

function HidecodeError() {
    codeError.style.display = "none";
}

function showError(message) {
    codeErrorMessageElement.textContent = message;
    codeError.style.display = "block";
}


});
