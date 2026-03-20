
/*
Template Name: Adminox - Responsive Bootstrap 4 Admin Dashboard
Author: CoderThemes
Version: 2.0.0
Website: https://coderthemes.com/
Contact: support@coderthemes.com
File: Form picker init js
*/

$(document).ready(function () {

    // Time Picker
    $('#timepicker').timepicker({
        defaultTIme: false,
        icons: {
            up: 'mdi mdi-chevron-up',
            down: 'mdi mdi-chevron-down'
        }
    });
    $('#timepicker2').timepicker({
        showMeridian: false,
        icons: {
            up: 'mdi mdi-chevron-up',
            down: 'mdi mdi-chevron-down'
        }
    });
    $('#timepicker3').timepicker({
        minuteStep: 15,
        icons: {
            up: 'mdi mdi-chevron-up',
            down: 'mdi mdi-chevron-down'
        }
    });

    //colorpicker start

    $('.colorpicker-default').colorpicker({
        format: 'hex'
    });
    $('.colorpicker-rgba').colorpicker();


    // Date Picker
    $('#datepicker').datepicker();
    $('#datepicker-autoclose').datepicker({
        autoclose: true,
        todayHighlight: true
    });
    $('#datepicker-inline').datepicker();
    $('#datepicker-multiple-date').datepicker({
        format: "mm/dd/yyyy",
        clearBtn: true,
        multidate: true,
        multidateSeparator: ","
    });
    $('#date-range').datepicker({
        toggleActive: true
    });
    

    //Clock Picker
    $('.clockpicker').clockpicker({
        donetext: 'Done'
    });

    $('#single-input').clockpicker({
        placement: 'bottom',
        align: 'left',
        autoclose: true,
        'default': 'now'
    });
    $('#check-minutes').click(function (e) {
        // Have to stop propagation here
        e.stopPropagation();
        $("#single-input").clockpicker('show')
            .clockpicker('toggleView', 'minutes');
    });


    //Date range picker
    $('.input-daterange-datepicker').daterangepicker({
        buttonClasses: ['btn', 'btn-sm'],
        applyClass: 'btn-success',
        cancelClass: 'btn-light'
    });
    $('.input-daterange-timepicker').daterangepicker({
        timePicker: true,
        timePickerIncrement: 30,
        locale: {
            format: 'YYYY/MM/DD h:mm A'
        },
        buttonClasses: ['btn', 'btn-sm'],
        applyClass: 'btn-success',
        cancelClass: 'btn-light'
    });
    $('.input-limit-datepicker').daterangepicker({
        format: 'MM/DD/YYYY',
        minDate: '06/01/2018',
        maxDate: '06/30/2030',
        buttonClasses: ['btn', 'btn-sm'],
        applyClass: 'btn-success',
        cancelClass: 'btn-light',
        dateLimit: {
            days: 6
        }
    });

    $('#reportrange span').html(moment().subtract(29, 'days').format('YYYY-MM-DD') + ' - ' + moment().format('YYYY-MM-DD'));
	
	let startDate, endDate;
	
    $('#reportrange').daterangepicker({
        format: 'MM/DD/YYYY',
        startDate: moment().subtract(29, 'days'),
        endDate: moment(),
        minDate: '01/01/2017',
        maxDate: '12/31/2030',
        dateLimit: {
            days: 60
        },
        showDropdowns: true,
        showWeekNumbers: false,
        timePicker: false,
        timePickerIncrement: 1,
        timePicker12Hour: true,
        ranges: {
            'Today': [moment(), moment()],
            'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
            'Last 7 Days': [moment().subtract(6, 'days'), moment()],
            'Last 30 Days': [moment().subtract(29, 'days'), moment()],
            'This Month': [moment().startOf('month'), moment().endOf('month')],
            'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
        },
        opens: 'left',
        drops: 'down',
        buttonClasses: ['btn', 'btn-sm'],
        applyClass: 'btn-success',
        cancelClass: 'btn-light',
        separator: ' to ',
        locale: {
            applyLabel: 'Submit',
            cancelLabel: 'Cancel',
            fromLabel: 'From',
            toLabel: 'To',
            customRangeLabel: 'Custom',
            daysOfWeek: ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'],
            monthNames: ['Січень', 'Лютий', 'Березень', 'Квітень', 'Травень', 'Червень', 'Липень', 'Серпень', 'Вересень', 'Жовтень', 'Листопад', 'Грудень'],
            firstDay: 1
        }
    }, function (start, end, label) {		
		period = [start.format('YYYY-MM-DD'),end.format('YYYY-MM-DD')];
		console.log(period);
		if(label=='Today'){
			let previos_period = [moment().subtract(1, 'days').format('YYYY-MM-DD'), moment().subtract(1, 'days').format('YYYY-MM-DD')];
			console.log(previos_period);
		};
		if(label=='Yesterday'){
			let previos_period = [moment().subtract(2, 'days').format('YYYY-MM-DD'), moment().subtract(2, 'days').format('YYYY-MM-DD')];
			console.log(previos_period);
		};
		if(label=='This Month'){
			let previos_period = [moment().subtract(1, 'month').startOf('month').format('YYYY-MM-DD'), moment().subtract(1, 'month').endOf('month').format('YYYY-MM-DD')];
			console.log(previos_period);
		};
		
        $('#reportrange span').html(start.format('MMMM D, YYYY') + ' - ' + end.format('MMMM D, YYYY'));
		CreateReport(period);
    });

});