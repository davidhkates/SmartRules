/* jshint strict: false */

$(document).ready(function () {
	var scroll = $(window).scrollTop();
	$("html").scrollTop(scroll);

	$('.device_select').click(function() {
		$('#device').val(this.id);
		form.submit();

		var token = "8ecca2c7-daf4-4aef-bbed-0538b9906862";
		$.ajax({
			url: "https://api.smartthings.com/v1/devices?deviceId=".this.id,
			type: 'GET',
			// Fetch the stored token from localStorage and set in the header
			headers: {
				Authorization: 'Bearer '+token
			},
			error: function(err) {
				switch (err.status) {
					case "400":
						// bad request
						break;
					case "401":
						// unauthorized
						break;
					case "403":
						// forbidden
						break;
					default:
						//Something bad happened
						break;
				}
			},
			success: function(data) {
				console.log("Success!");
			}
		});
	});

	$('.rule_select').click(function () {
		$('#rule').val(this.id);
		var action = $('#action_' + this.id).html();
		$('#rule_actions').html(action);
	});

	$("#device").on('input', function() {
		form.submit();
	});

	$("select").click(function() {
		// $("#myForm").submit();
		form.submit();
	});

	/*
    function drag(ev) {
      ev.dataTransfer.setData("text", ev.target.id);
    }

    function drop(ev) {
        ev.preventDefault();
        let data = ev.dataTransfer.getData("text");
        //ev.target.appendChild(document.getElementById(data));
        ev.target.append(document.getElementById(data));
        // this.innerHTML = data;
        document.getElementById('ohdsi_code').value = data.substring(5);
    }
    */
});