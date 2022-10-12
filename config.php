<form id="config_form" action="" method="post">
    <input type="hidden" id="tab_shown" name="tab_shown" value="config"/>

    <?php
    $org_config = ($_POST['org_config'] ?: 'washu');
    $domain_config = ($_POST['domain_config'] ?: 'condition_occurrence');
    $org_specific = ($_POST['org_specific'] ?: false);

    $sql_silver = get_silver_sql($db, $domain_config, $org_config, $org_specific);
    if ($org_specific && $sql_silver=='') {
        $sql_silver = get_silver_sql($db, $domain_config, $org_config);
        $sql_silver = str_replace('$org', $org_config, $sql_silver);
    }
    $generate_sql = pg_bool(get_domain_info($db, $domain_config, 'generate_sql'));

    switch ($_POST['submit_config']) {
        case 'Clear':
            clear_config($db, $org_config, $domain_config, $org_specific);
            break;
        case 'Apply':
            if ($generate_sql) {
                $sql_silver = build_pipeline_sql($db, $_POST, $domain_config, $org_config, $org_specific);
            }
            break;
        case 'Save':
            save_config($db, $_POST, $domain_config, $org_config, $org_specific);
            if ($generate_sql) {
                $sql_silver = build_pipeline_sql($db, $_POST, $domain_config, $org_config, $org_specific);
            }
            break;
        case 'Lookup':
            break;
    }
    ?>

    <table style="layout-table;">
        <tr>
            <td colspan="3" style="background-color: #336791; color: white;"><h2>Data Factory Configuration</h2></td>
        </tr>
        <tr>
            <td style="width:15%; vertical-align:top;">
                <label for="config_domain" style="font-weight: bold;">OMOP&nbsp;data&nbsp;domain:</label><br/>
                <?php domains_radio_group($db, 'domain_config', 'config', $domain_config); ?>

                <p><input type="button" name="submit_config" value="Clear">
                    <input type="button" name="submit_config" value="Apply">
                    <input type="submit" name="submit_config" value="Save"></p>

                <p><input type="checkbox" name="org_specific" onclick="this.form.submit();"
                    <?php if ($org_specific) { echo " checked"; } ?> />
                    <label for="org_specific" style="font-weight: bold;">Apply&nbsp;to&nbsp;specific&nbsp;org:</label><br/>
                    <select name="org_config" id="org_config" onchange="this.form.submit();"
                    <?php
                    if (!$org_specific) { echo " disabled"; }
                    echo ">";
                    orgs_dropdown_group($db, $org_config);
                    ?>
                    </select></p>

                <p><i>Used by the technology team to set up pipeline ETL.</i></p>

            </td>
            <td style="width:80%; vertical-align:top;">
                <h3>Data Import Definitions</h3>
                <div class="tableFixHead">
                    <table style="width:100%;">
                        <thead>
                        <tr>
                            <th>Source Column</th>
                            <th>Data Type</th>
                            <th>Required?</th>
                            <th>Display Heading</th>
                            <?php if ($org_specific) { echo "<th>Org-Specific Field Name</th>"; } ?>
                        </tr>
                        </thead>
                        <tbody><?php display_config_import($db, $domain_config, $org_config, $org_specific); ?></tbody>
                    </table>
                </div>

                <h3>Data Export Definitions</h3>
                <div class="tableFixHead">
                    <table style="width:100%;">
                        <thead>
                        <tr>
                            <th>Source Column</th>
                            <th>Display in Silver Zone</th>
                            <th>Include in Output to SBPLA (S3)</th>
                            <th>Display Heading</th>
                        </tr>
                        </thead>
                        <tbody><?php display_config_output($db, $domain_config, $org_specific); ?></tbody>
                    </table>
                </div>

                <h3>SQL Statement to Transform Source Data into Pipeline</h3>
                    <textarea id="sql_silver" name="sql_silver" rows="10" cols="200"><?php echo $sql_silver; ?></textarea>
            </td>
            <td style="width:5%;">&nbsp;</td>
        </tr>
    </table>
</form>
