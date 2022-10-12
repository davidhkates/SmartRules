<form id="quality_form" action="" method="post">
    <input type="hidden" id="tab_shown" name="tab_shown" value="quality"/>

    <?php
    // print_r ($_POST);

    $org_quality = ( $_POST['org'] ?: $_POST['org_quality']);
    $domain_quality = ( $_POST['domain'] ?: $_POST['domain_quality']);
    ?>

    <table style="layout-table;">
	<tr><td colspan="3" style="background-color:#336791; color:#FFFFFF;"><h2>Data Quality Analysis (<?php echo $_POST['org']; ?>, <?php echo $_POST['domain']; ?>)</h2></td></tr>
	<tr>
		<td style="width:5%; vertical-align:top;">
            <p><label for="org" style="font-weight: bold;">Choose&nbsp;an&nbsp;organization:</label>
                <select name="org_quality" id="org_quality" onchange="form.submit();">
                    <?php orgs_dropdown_group($db, $org_quality); ?>
                </select><label for="org_quality"></label>
                <!--input type="submit" name="submit_details" value="Update"--></p>

            <label for="quality_type" style="font-weight: bold;">Data issue type:</label><br/>
            <?php
            radio_option('quality_type', 'missing', 'Missing', true);
            radio_option('quality_type', 'mismatch', 'Mismatched');
            radio_option('quality_type', 'malformed', 'Wrong Type', false, true);
            radio_option('quality_type', 'invalid', 'Invalid', false, true);
            ?>
            <p><input type="submit" name="submit" value="Export"></p>

        </td>
		<td style="width:90%;">
			<h3>Missing Data</h3>
			<div class="tableFixHead">
			<table id="" class="display" style="width:100%;">
				<thead><tr><th>UPN ID</th><th>Person ID</th><th>Field</th></tr></thead>
				<?php display_missing_table($db, $org, $domain); ?>
			</table>
			</div>

			<h3>Discrepancies</h3>
			<input type="checkbox" id="case_sensitive" name="case_sensitive" value="case_sensitive" checked>
			<label for="case_sensitive"> Comparison is case sensitive</label><br>
			<div class="tableFixHead">
			<table id="mismatch_data_table" class="display" style="width:100%;">
				<thead><tr><th>UPN ID</th><th>Person ID</th><th>Field</th><th>Source Data</th><th>Mapped Data</th></tr></thead>
				<?php display_mismatch_table($db, $org, $domain); ?>
			</table>
			</div>

            <h3>Constraint Errors</h3>
            <div class="tableFixHead">
                <table id="" class="display" style="width:100%;">
                    <thead><tr><th>Domain</th><th>Type</th><th>Key Field</th><th>Value</th><th>Count</th></tr></thead>
                    <?php
                    display_duplicate_keys($db, $org, 'location');
                    display_missing_index($db, $org, 'person', 'death', 'person_id');
                    display_missing_index($db, $org, 'person', 'location', 'location_id');
                    ?>
                </table>
            </div>

        </td>
		<td style="width:5%;">&nbsp;</td>
	</tr>
</table>
<!--/form-->

