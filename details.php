<form id="details_form" action="" method="post">
    <input type="hidden" id="tab_shown" name="tab_shown" value="details" />

    <?php
    $org = ($_POST['org'] ?: 'washu');
    // $domain = ($_POST['domain'] ?: 'person' );
    $domain = ($_POST['domain'] ?: ($_POST['domain_map'] ?: 'person') );
    $domain_header = "<span style='color:darkblue;'>" . strtoupper($domain) . "</span>";

    switch ($_POST['submit_details']) {
        case 'Clear':
            delete_pipeline_tables($db, $org, $domain);
            break;
        case 'Load':
            delete_pipeline_tables($db, $org, $domain);
            load_pipeline_bronze($db, $org, $domain);
            break;
        case 'Run':
            delete_pipeline_silver($db, $org, $domain);
            load_pipeline_silver($db, $org, $domain);
            load_pipeline_gold($db, $org, $domain);
            break;
        case 'Harmonize':
            harmonize_code_vocab($db, $org, $domain);
            break;
        case 'Push':
            push_pipeline_tables($db, $domain);
            break;
    }
    ?>

    <table style="layout-table;">
        <tr>
            <td colspan="3" style="background-color: #336791; color: white;"><h2>Data Pipeline Details
                    (<?php echo $org . ', ' . $domain; ?>)</h2></td>
        </tr>
        <tr>
            <td style="width:5%; vertical-align:top;">
                <p><label for="org" style="font-weight: bold;">Choose&nbsp;an&nbsp;organization:</label>
                <select name="org" id="org" onchange="form.submit();">
                    <?php orgs_dropdown_group($db, $org); ?>
                </select>
                    <!--input type="submit" name="submit_details" value="Update"--></p>

                <label for="domain" style="font-weight: bold;">OMOP data domain:</label><br/>
                <?php domains_radio_group($db, 'domain', 'pipeline', $domain); ?>

                <p><input type="submit" name="submit_details" value="Clear" title="Clear bronze and silver zone">
                    <input type="submit" name="submit_details" value="Load" title="Load data from S3 into bronze">
                    <input type="submit" name="submit_details" value="Run" title="Run SQL to populate silver and gold">
                    <input type="submit" name="submit_details" id="details_harmonize" value="Harmonize"
                           title="Map concepts to target vocabulary" <?php harmonize_domain_disabled($db, $domain); ?>>
                    <input type="submit" name="submit_details" value="Push" title="Push data from gold zone to SBPLA S3"></p>
                <input type="hidden" id="selected_record" name="selected_record"/>
                <!--p><input type="submit" name="lookup" value="Lookup"></p-->

                <!--Drag concept for mapping here
                <div id="dropzone" ondrop="drop(event)" ondragover="allowDrop(event)" style="background-color: orange">&nbsp;</div> -->
                <div><label><input type="checkbox" name="hide_bronze"
                            onclick="this.form.submit();" <?php if ($_POST['hide_bronze']) {echo " checked";} ?> />Hide raw data</label></div>

                <p><i>Visualizes running the data pipeline for selected health system source and data domain.</i></p>

            </td>
            <td style="width:90%;">
                <?php
                // Display data in bronze zone for selected domain, if show bronze checked
                if (!$_POST['hide_bronze']) {
                    echo "<h3>{$domain_header} Raw Data (Bronze)</h3>";
                    // display_domain_table($db, $org, $domain, 'bronze');
                    display_pipeline_bronze($db, $org, $domain);
                }

                // Display data in silver zone for selected domain
                echo "<h3>{$domain_header} Structured Data (Silver)</h3>";
                display_pipeline_zone($db, $org, $domain, 'silver');
                // display_pipeline_silver($db, $org, $domain);

                // Display data in gold zone for selected domain
                echo "<h3>{$domain_header} SBPLA Output Data (Gold)</h3>";
                display_pipeline_zone($db, $org, $domain, 'gold');
                // display_pipeline_gold($db, $org, $domain);

                // Show data in JSON format for details tables (not person or visit)
                if ($domain != 'person' && $domain != 'visit') {
                    echo '<h3>Output in JSON Format</h3>';
                    echo '<textarea id="omop_json" name="omop_json" rows="10" cols="200">';
                    output_to_json($db, $org, $domain);
                    echo '</textarea>';
                }
                ?>
            </td>
            <td style="width:5%;">&nbsp;</td>
        </tr>
    </table>
</form>
