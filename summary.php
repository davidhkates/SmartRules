<form id="summary_form" action="" method="post">
    <input type="hidden" id="tab_shown" name="tab_shown" value="summary"/>

    <?php
    $org_summary = ( $_POST['org_summary'] ?: 'washu' );

    // No special case for Update which will occur as a byproduct of submitting form
    switch (strtok($_POST['submit_summary'], " ")) {

        case 'Run':     // Run pipeline end-to-end
            /**********************************************************************************************
             * The following routines perform the full automation of the pipeline and comprise the
             * functions that should be invoked on receipt and unzipping of the data files from a
             * source health system.  In short, they perform the following in this order for the
             * "current" organization specified in $org_summary (from the options list box on this page).
             *
             * NOTE: each of the routines below performs its functions on ALL the domains marked as "in_pipeline"
             * in the config.domain table.  Note also, that the load_pipeline function references the
             * config.s3_import table to determine which files from the zip file to include for a given domain.
             *
             *    DELETE_PIPELINE_TABLES - Delete all records in the bronze and silver tables for this source
             *         for all the domains marked as "in_pipeline" in config.domain table
             *    LOAD_PIPELINE_BRONZE - Import records from S3 into the applicable bronze zone (i.e.,
             *         source_mnemonic.table_name) per the files stipulated in config.s3_import for each domain
             *         (for example, WashU's person tables are loaded into washu.person, washu.mrn, washu,death,
             *         and washu.location)
             *
             *    LOAD_PIPELINE_SILVER - Denormalize and de-identify (obfuscate) data in bronze zone into
             *         corresponding silver zone tables. NOTE: the source org is a column/attribute in each of
             *         silver and gold tables, thereby "logically" separating the data (not to mention that UPN
             *         ID's are unique across all orgs and can be used to find the source org).  The obfuscation
             *         is done in SQL leveraging the mapping from MRN to UPN_ID (from the Propel eConsent system),
             *         the mapping from MRN to Person_ID (in the bronze MRN table), and the offset days (from -365
             *         to +365 randomly generated when a new person/patient enrolls in the UPN - which is a TODO
             *         that needs to be automated - currently performed in dBeaver).
             *
             *         The rules for type casting and denormalization of the other fields in the bronze data is
             *         determined based on the JOINS and SELECT phrases in the SQL for this  domain and source
             *         organization in config.import_sql.  This SQL is generated from the field
             *         attributes assigned by the data team user(s) in the config.php page and associated
             *         pipeline_config.php code which is where the corresponding business logic is explained.
             *
             *    HARMONIZE_CODE_VOCAB - Maps all concepts in the source domains listed as 'select_for_mapping' in
             *         the config.domain table to the target vocabulary (listed in that same table).  Mapping may
             *         be a misnomer here as it is actually looking at all records in the silver table which have a
             *         vocabulary_id other than the target vocabulary and then ADDS records that correspond to that
             *         same (or similar) concept in the target vocabulary.  This mapping uses the
             *         athena.concept_relationship and athena.concept_ancestor files.  The business logic for that
             *         mapping is described in the pipeline_mapping.php code.
             *
             *    LOAD_PIPELINE_GOLD - This function perfors the upsert of data from the silver zone into the gold
             *         zone.  It includes the harmonized concepts for that domain, marked as 'inferred'.  NOTE: by
             *         virtue of performing an upsert v. a drop/delete and replace is that patients that have been
             *         removed from the bronze and silver tables - e.g., because they are no longer part of an
             *         active study or have opted out - will remain in the gold table and be included in the export
             *         to the SBPLA S3 bucket in the next function which is the intended behavior.
             *
             *     PUSH_PIPELINE_TABLE - This last step performs the export of the data in the gold tables to the
             *          S3 bucket used by SBPLA.  The columns in the Gold table that will be imported for each
             *          domain are determined by the columns in the config.field_output table as defined by the
             *          data team configuring the pipeline.  TODO: we still need to determine whether there will
             *          be a separate S3 output bucket for each source health sytem or one consolidate S3 bucket
             *          for ALL health systems which will require modifications to this routine.
             *
             */
            echo 'Deleting pipeline tables';
            delete_pipeline_tables($db, $org_summary);
            load_pipeline_bronze($db, $org_summary);
            load_pipeline_silver($db, $org_summary);
            harmonize_code_vocab($db, $org_summary);
            load_pipeline_gold($db, $org_summary);
            push_pipeline_tables($db);
            break;

        case 'Update':
            update_athena_files($db);
            break;

        case 'Generate':
            /*
             * Currently the code that generates the pipeline SQL is heavily interactive and uses the config
             * settings as shown/selected on the config.php page to iterate through the fields in the SQL.
             * Automating that here would be a massive refactoring effort so the following instructions should
             * instead be followed before running the pipeline:
             *
             *     1. Go to the config tab of the pipeline dashboard
             *     2. Select each domain that is currently included in the pipeline (currently person,
             *        visit, condition, procedure, and medication)
             *     3. If not already configured, select the transformation that should be performed on
             *        each of the source fields.  The choices are based on the field name as follows:
             *            IDs - OHDSI Concept (denormalize name, vocabulary, standard_concept, and target_code);
             *                  ODHSI Attribute (denoramlize to include name/description along with ID)
             *            Date/Time - Date or Date/Time with specified date format subtype
             *            Site/Location - Lookup in location table
             *            Provider - Lookup in provider table (when provided in future)
             *            All - String (text/varchar), Numeric (int8), or N/A (ignore, i.e., not provided)
             *     5. Once the transform configs are defined, select the fields that should be included in
             *        the silver table and gold table display on the pipeline table.  NOTE: all fields are
             *        included in silver/gold tables... the config just determines which will be shown and,
             *        for the Gold table, which will be exported to SBPLA.
             *     6. After defining the configuration, press the "Save" button to save these setting.  This
             *        will save/update the pipeline_import_sql and pipeline_process_sql that handles ETL and
             *        harmonization activities for those domains (along with the other config-driven behaviors)
             *
             * NOTE: the OHDSI reference files from Athena should also be updated prior to running the
             *       pipeline if there have been updates since they were last imported.
             *
             */
            // generate_pipeline_sql($db);
            break;

        case 'Pull':
            delete_pipeline_tables($db, $org_summary);
            load_pipeline_bronze($db, $org_summary);
            break;

        case 'Load':
            load_pipeline_silver($db, $org_summary);
            load_pipeline_gold($db, $org_summary);
            break;

        case 'Harmonize':
            // harmonize_code_vocab($db, $org_summary, 'procedure_occurrence');
            harmonize_code_vocab($db, $org_summary);
            break;

        case 'Push':
            push_pipeline_tables($db);
            break;
    }
    ?>

    <table style="layout-table;">
        <tr>
            <td colspan="3" style="background-color: #336791;"><h2 style="color: white;">Inventory of Data Assets</h2>
            </td>
        </tr>
        <tr>
            <td style="width:5%; vertical-align:top;">&nbsp;
                <p><label for="org" style="font-weight: bold;" onchange="form.submit();">Choose&nbsp;an&nbsp;org:</label>
                    <select name="org_summary" id="org">
                        <?php orgs_dropdown_group($db, $org_summary); ?></select>
                    <!--input type="submit" name="submit_summary" value="Update"--></p>
            </td>
            <td style="width:90%;">
                <!--h3>Data Pipeline Operations Workflow</h3-->
                <table style="layout-table; background-color:#EEEEEE;">
                    <thead><tr>
                        <th style="align-content:center; background-color:#FFEB9C; border-color:#FFFFFF;">Fully Automated Data Operations</th>
                        <th style="align-content:center; background-color:#FFEB9C; border-color:#FFFFFF;">Staged Pipeline Processing</th>
                    </tr></thead>
                    <tbody><tr>
                        <td><input type="submit" class="button-workflow button-automation" name="submit_summary" value="Run pipeline"/>&nbsp;
                            <input type="submit" class="button-workflow button-automation" name="submit_summary" id="load_athena"
                                   value="Update Athena files" title="Load OHDSI reference files zipped in S3 input bucket"/>&nbsp;
                            <input type="submit" class="button-workflow button-automation" name="submit_summary" value="Generate pipeline SQL"
                                   title="Apply current config settings to SQL in config.pipeline_*_sql" disabled/>&nbsp;
                            <input type="submit" class="button-workflow button-automation" name="submit_summary" value="Process eConsent"/></td>
                        <td><input type="submit" class="button-workflow" name="submit_summary" value="Pull source files from S3"/>&nbsp;
                            <input type="submit" class="button-workflow" name="submit_summary"
                               value="Load silver and gold zones"/>&nbsp;
                            <input type="submit" class="button-workflow" name="submit_summary"
                               value="Harmonize to standard vocabularies"/>&nbsp;
                            <input type="submit" class="button-workflow" name="submit_summary"
                               value="Push to S3 for SBPLA"/></td>
                    </tr></tbody>
                    </table>

                <h3>Overview</h3>
                <div class="tableFixHead" style="height: 106px;">
                    <table style="width:100%;">
                        <thead>
                        <tr>
                            <th>Organization</th>
                            <th>Patients</th>
                            <th>Visits</th>
                            <th>Conditions</th>
                            <th>Procedures</th>
                            <th>Drugs</th>
                        </tr>
                        </thead>
                        <?php display_summary_tables($db); ?>
                    </table>
                </div>

                <h3>Patients</h3>
                <div class="tableFixHead" style="height: 106px;">
                    <table style="width:100%;">
                        <thead>
                        <tr>
                            <th>Organization</th>
                            <th>Count</th>
                        </tr>
                        </thead>

                        <?php
                        $query = "select source_org, count(upn_id) ";
                        $query .= "from silver.person ";
                        $query .= "group by source_org;";
                        display_table($db, $query);
                        ?>

                    </table>
                </div>

                <h3>Procedures</h3>
                <div class="tableFixHead" style="height: 200px;">
                    <table style="width:100%;">
                        <thead>
                        <tr>
                            <th>Vocabulary</th>
                            <th>Count</th>
                        </tr>
                        </thead>
                        <tbody>

                        <?php
                        $query = "select procedure_concept_vocabulary_id, count(*) from silver.procedure_occurrence ";
                        $query .= "group by procedure_concept_vocabulary_id;";
                        display_table($db, $query);
                        ?>
                        </tbody>
                    </table>
            </td>
            <td style="width:5%;">&nbsp;</td>
        </tr>
    </table>
</form>
