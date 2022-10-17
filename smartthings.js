/* jshint strict: false */

$(document).ready(function () {
	var scroll = $(window).scrollTop();
	$("html").scrollTop(scroll);

	$("select").click(function() {
		// $("#myForm").submit();
		form.submit();
	});

	$('.rule_select').click(function () {
		$('#rule').val(this.id);
		var action = $('#action_' + this.id).html();
		$('#rule_actions').html(action);
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