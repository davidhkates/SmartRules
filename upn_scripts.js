/* jshint strict: false */

$(document).ready(function () {
	var scroll = $(window).scrollTop();
	$("html").scrollTop(scroll);

	$('#silver-pipeline-table').dataTable({
		stateSave: true
	});

	/*
	$('#map-concepts').click(function() {
		$("#mapping_form").submit();
		// window.alert('Map concepts button pressed');
		showSection('details');
		$("#details_form").submit();
	});
	 */

	/*
	$('#bronze-pipeline-table').DataTable({
		responsive: true,
		scrollY: '200px',
		scrollX: true,
		scrollCollapse: true,
		paging: false,
	});

	$('#silver-pipeline-table').DataTable({
		responsive: true,
		scrollY: '200px',
		scrollX: true,
		scrollCollapse: true,
		paging: false,
	});

	$('#gold-pipeline-table').DataTable({
		responsive: true,
		scrollY: '200px',
		scrollX: true,
		scrollCollapse: true,
		paging: false,
	});
	 */

	$('#mapping_data_table').DataTable({
		pageLength: '25',
	});

	$("nav li" ).click(function() {
		var tabForm =  $(this).attr('name') + "_form";
		if($("#" + tabForm).length !== 0) {
			$('#' + tabForm).submit();
		}
		showSection($(this).attr('name'));
	});

	$('#details_harmonize').click(function() {
		$('#wait_spinner').show();
	});

	$('#load_athena').click(function() {
		$('#status_message').show();
		// document.getElementById('status_area').src = 'status_update.php';
	});
});

// function showSection(tab_id, form_id) {
function showSection(tabID) {
	"use strict";
	document.getElementById('details').style.display = 'none';
	document.getElementById('summary').style.display = 'none';
	document.getElementById('quality').style.display = 'none';
	document.getElementById('mapping').style.display = 'none';
	document.getElementById('projects').style.display = 'none';
	document.getElementById('config').style.display = 'none';
	document.getElementById('overview').style.display = 'none';
	document.getElementById(tabID).style.display = 'block';
}

var mapping_form = document.getElementById("mapping-form");
document.getElementById("concept-record").addEventListener("click", function () {
	mapping_form.submit();
});

/*
function onchange(e) {
	window.location.reload();
}

/* Drag and drop functionality - not currently being used
function allowDrop(ev) {
  ev.preventDefault();
}

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


// Timer functions and variables
var progressRow = 0;
var progressCounter = 1;

function progressTimer() {
	progressCounter++;
	const counterMinutes = ('0' + Math.floor(progressCounter / 60).toString()).slice(-2);
	const counterSeconds = ('0' + (progressCounter % 60).toString()).slice(-2);
	const progressField = '#progress_elapsed_' + progressRow.toString();
	// $('#progress_elapsed_0').html(counterMinutes + ':' + counterSeconds);
	$(progressField).html(counterMinutes + ':' + counterSeconds);
}

function setProgressRow(row) {
	progressRow = row;
}

function clearCounter() {
	progressCounter = 1;
}

function getLocalTime() {
	// const localDateTime = new Date().toLocaleString();
	const localTime = new Date().toLocaleTimeString();
	return localTime;
}
