jQuery(document).ready(function($) {

	// # datetimepicker jquery-ui add-on with additional parms for
	// # date and time formating 
	// # logic to prevent selection of conflicting dates (start after end date mistakes)

	// # grab the time string from end date - format (8:00am)
	var startTime = validPickerTime($('[name="pcr-sched-post_startdate"]').val());

	$('[name="pcr-sched-post_startdate"]').change(function() {
		startTime = validPickerTime($('[name="pcr-sched-post_startdate"]').val());
		
		// # grab the dates onchange for validation
		var startdate = convertDate($('[name="pcr-sched-post_startdate"]').val());
		var enddate = convertDate($('[name="pcr-sched-post_enddate"]').val());

		// # if end date feild is populated, check to make sure its never less than start date
		if($('[name="pcr-sched-post_enddate"]').val() && startdate > enddate) {
			$('[name="pcr-sched-post_enddate"]').val($('[name="pcr-sched-post_startdate"]').val());
		}
		
		// # un-disable clear button if start date is populated.
		if($('[name="pcr-sched-post_startdate"]').val() != '') {
			$('#pcr-sched-post_clearDates').removeAttr('disabled');
		}
	});
	
	var endTime = validPickerTime($('[name="pcr-sched-post_enddate"]').val());

	$('[name="pcr-sched-post_startdate"], [name="alert_date"]').datetimepicker({
		dateFormat : 'mm/dd/yy',
		timeFormat:  "h:mmtt",
		changeMonth: true,
		changeYear: true,
		minDateTime: $('[name="pcr-sched-post_enddate"]').datetimepicker("getDate"),
    	maxDate: '+2y',
	    onSelect: function(date) { // # date is a string and must be converted

	    	// # use the function below to convert string datetime into js Date object
			date = convertDate(date);
	        var endDate = new Date(date.getTime());

	        // # we must use minDate and NOT minDateTime for this to work
	        $('[name="pcr-sched-post_enddate"]').datetimepicker( "option", "minDate", endDate);
	        $('[name="pcr-sched-post_enddate"]').datetimepicker( "option", "maxDate", '+2y' );
	        $('[name="pcr-sched-post_enddate"]').datetimepicker( "option", "minTime", startTime );


	        if($('[name="pcr-sched-post_enddate"]').val() && date > endDate) {
				$('[name="pcr-sched-post_enddate"]').val($('[name="pcr-sched-post_startdate"]').val());
			}
		}
	});

	$('[name="pcr-sched-post_enddate"]').datetimepicker({ 
		dateFormat : 'mm/dd/yy',
		timeFormat:  "h:mmtt",
	    changeMonth: true,
	   	changeYear: true,
	   	minDateTime: $('[name="pcr-sched-post_startdate"]').datetimepicker("getDate"),
		minTime: startTime,
		onSelect: function(date) {

			date = convertDate(date);
	        var endDate = new Date(date.getTime());

	        // # we must use minDate and NOT minDateTime for this to work

	        $('[name="pcr-sched-post_startdate"]').datetimepicker( "option", "maxDate", endDate);

		}
	});


	// # clear dates and status
	$('#pcr-sched-post_clearDates').click(function(event) {
		event.preventDefault();
		$('[name="pcr-sched-post_startdate"], [name="pcr-sched-post_enddate"], #pcr-sched-post_select_status').val('');
		$('#pcr-sched-post_select_status').find('option:selected').removeAttr("selected");
		$('#pcr-sched-post_select_status').attr('disabled', 'disabled');
		$('#pcr-sched-post_clearDates').attr('disabled', 'disabled');
		return false;
	});


	// # style the pulldown options with colors and disable inputs for value = null

	$('select.pcr-sched-post_select_status option:first-child').css('color','gray');

	$('select.pcr-sched-post_select_status').change(function() {

	    var val = $(this).val();

	   	if(val == '') {
	    	//$('select.pcr-sched-post_select_status').css('background', 'red');
			//$(this).find('option').css('background-color', 'red');
			$(this).find('option:selected').css('color', 'red');
			$(this).find('option:selected').attr('disabled','disabled');
			$(this).find('option').attr("selected",false);
			$('#pcr-sched-post_select_status').attr('required', 'required');
	    } else {
			//$('select.pcr-sched-post_select_status').css('background', 'transparent');
			$(this).find('option').css('color', 'black');
			$(this).find('option:first-child').css('color', 'gray');
			$('#pcr-sched-post_select_status').removeAttr('required');

			// # pass the checkRequired function the element
			checkRequired(this);
	    }
	});

	// # set the default expiration status select to read only if expire date is null

	if($('[name="pcr-sched-post_enddate"]').val() == '') {
		$('#pcr-sched-post_select_status').attr('disabled', 'disabled').removeAttr('required');
	} else {
		$('#pcr-sched-post_select_status').attr('required', 'required').removeAttr('disabled');
		$('#pcr-sched-post_clearDates').removeAttr('disabled');
	}

	$('[name="pcr-sched-post_enddate"]').change(function() {

		if($('[name="pcr-sched-post_enddate"]').val() == '') {
			$('#pcr-sched-post_select_status').attr('disabled', 'disabled').removeAttr('required');

		} else {

			$('#pcr-sched-post_select_status').attr('required', 'required').removeAttr('disabled');
			$('#pcr-sched-post_clearDates').removeAttr('disabled');
			checkRequired($('#pcr-sched-post_select_status').get(0));
		}
	});

	if($('[name="pcr-sched-post_startdate"]').val() == '' && $('[name="pcr-sched-post_enddate"]').val() == '') {
		$('#pcr-sched-post_clearDates').attr('disabled', 'disabled');
	}

/*
	$('[name="post"]').on("submit",function(e){
		//e.preventDefault(); // THIS WILL PREVENT POST SAVING! FOR DEBUG
		console.log('here');
		
	});
*/


});

function checkRequired(input) {

    if(input.validity.valueMissing){  
        input.setCustomValidity("You must select an expired status if you use an expiration date.");  
    } else {  
        input.setCustomValidity("");  
    }                 
} 


function convertDate(date) {

	if(!date) return;

	// # valid js Date and time object format (YYYY-MM-DDTHH:MM:SS)
	var dateTimeParts = date.split(' ');
  
  	// # note - this will break if the timeFormat parm of datetimepicker is changed above!
  	// # this assumes time format has NO SPACE between time and am/pm marks.
	if(dateTimeParts[1].indexOf(' ') == -1 && dateTimeParts[2] === undefined) {

		var theTime = dateTimeParts[1];

		// # strip out all except numbers and colon
	    var ampm = theTime.replace(/[0-9:]/g,'');

	    // # strip out all except letters (for AM/PM)
	    var time = theTime.replace(/[[^a-zA-Z]/g,'');
	  
	    if(ampm == 'pm') {

			time = time.split(':');

			// # if PM is less than 22:00 hours
			if(time[0] == 12) {
				time = parseInt(time[0]) + ':' + time[1] + ':00';
			} else {
				time = parseInt(time[0]) + 12 + ':' + time[1] + ':00';
			}

		} else { // if AM

			time = time.split(':');

			// # if AM is less than 10 o'clock, add leading zero
	    	if(time[0] < 10) {
				time = '0' + time[0] + ':' + time[1] + ':00';
	      	} else {
				time = time[0] + ':' + time[1] + ':00';
	      	}
		}
	}


	// # create a new date object from only the date part
	var dateObj = new Date(dateTimeParts[0]);

	// # add leading zero to date of the month if less than 10
	var dayOfMonth = (dateObj.getDate() < 10 ? ("0"+dateObj.getDate()) : dateObj.getDate());

	// # parse each date object part and put all parts together
  	var yearMoDay = dateObj.getFullYear() + '-' + (dateObj.getMonth() + 1) + '-' + dayOfMonth;

  	// # finally combine re-formatted date and re-formatted time!
	var date = new Date(yearMoDay + 'T' + time);

	// # use timezone offset to get accurate time!
	var tzoffset = date.getTimezoneOffset();
  	date = new Date(date.getTime() + (tzoffset * 60000));

	return date;
}

function validPickerTime(time) {

	var time = time.split(' ')[1];
	if(time != undefined) {
		// # strip out all except numbers and colon
		var timeAMPM = time.replace(/[0-9:]/g,'');
		// # strip out all except letters (for AM/PM) and add AM/PM with space
		time = time.replace(/[[^a-zA-Z]/g,'') + ' ' + timeAMPM;
		// # replace all white space for single space
		time = time.replace(/ +(?= )/g,'');
	}

	return time;
}