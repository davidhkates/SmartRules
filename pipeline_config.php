<?php
declare(strict_types=1);

/****************************************************************************************
 * CONFIGURATION SETTINGS FOR IMPORT FROM S3, DISPLAY IN PIPELINE, AND OUTPUT TO SBPLA
 ****************************************************************************************/

// This function is used to display and configure the user-defined field type settings for each field in the
// specified DOMAIN.  By default, this applies to all sources for that domain, unless the ORG_SPECIFIC flag
// is set, in which case it only applies to that specific organization
function display_config_import($db, $domain, $org, $org_specific=false):void
{
    // get field import configuration information from config table
    $org_specific_query = $org_specific;
    if ($org_specific_query) {
        $query = "select co.column_name, fi.data_type, data_subtype, required, display_heading, field_name, co.ordinal_position ";
        $query .= "from information_schema.columns co left join config.field_import fi on co.column_name=fi.column_name ";
        $query .= "where co.table_schema='cdm' and co.table_name='{$domain}' and fi.config_domain='{$domain}' and fi.config_org='{$org}' ";
        $query .= "order by co.ordinal_position;";
        $result = execute_query($db, $query);
        // if no records founds, set org-specific query flag to false (i.e., query for CDM fields)
        $org_specific_query = (pg_num_rows($result) != 0);
    }

    if (!$org_specific_query) {
        $query = "select co.column_name, fi.data_type, data_subtype, required, display_heading, field_name, co.ordinal_position ";
        $query .= "from information_schema.columns co left join config.field_import fi on co.column_name=fi.column_name ";
        $query .= "where co.table_schema='cdm' and co.table_name='{$domain}' and fi.config_domain='{$domain}' and fi.config_org='cdm' ";
        $query .= "order by co.ordinal_position;";
        $result = execute_query($db, $query);
    }

    // if this is a new domain, get the columns from the raw table schema
    $new_domain = (pg_num_rows($result) == 0);
    if ($new_domain) {
        $query = "select column_name, ordinal_position from information_schema.columns ";
        $query .= "where table_schema='" . $org . "' and table_name='" . $domain . "' ";
        $query .= "order by ordinal_position;";
        // echo 'Domain schema table query: ' . $query . '<br/>';
        $result = execute_query($db, $query);

        if (pg_num_rows($result)==0) {
            $query = "select column_name, ordinal_position from information_schema.columns ";
            $query .= "where table_schema='cdm' and table_name='" . $domain . "' ";
            $query .= "order by ordinal_position;";
            $result = pg_query($db, $query);
        }
    }

    // display list of fields with options
    if ($result) {
        $row_number = 0;

        // first field should always be primary key
        $row = pg_fetch_assoc($result);
        display_config_row($row, $row_number, 'Primary Key', true, true, $org_specific);
        $row_number++;

        // next row is person_id (unless this is the person table)
        if ($domain != 'person') {
            $row = pg_fetch_assoc($result);
            display_config_row($row, $row_number, 'Person ID', true, true, $org_specific);
            $row_number++;
        }

        // get remaining rows
        while ($row = pg_fetch_assoc($result)) {
            if ($new_domain) {
                display_config_row($row, $row_number);
            } else {
                display_config_row($row, $row_number, '', pg_bool($row['required']), false, $org_specific);
            }
            $row_number++;
        }
    }
}

// This function is called by the display_config_domain function above to render the applicable options
// and current settings for the specified field in the current $ROW
function display_config_row($row, $row_number, $type='', $required=false, $disabled=false, $org_specific=false):void
{
    // $row = pg_fetch_assoc($result);
    echo '<tr><td>' . $row['column_name'] . '</td>';

    // display schema_element drop_down list options unless type is specified
    if ($type == '') {
        $element_name = $row['column_name'];
        $element_datatype = $row['data_type'];
        echo '<td><select name="schema_element[]" id="' . $row['ordinal_position'] . '" style="width:120px;">';
        if ($element_name=="provider_id") {
            dropdown_option('provider', 'Provider', $element_datatype);
        } elseif ($element_name=="location_id" || substr($element_name,-8)=="_site_id") {
            dropdown_option('location', 'Location', $element_datatype);
        } elseif (substr($element_name, -3)=="_id") {
            dropdown_option('ohdsi_concept', 'OHDSI Concept', $element_datatype);
            dropdown_option('ohdsi_attribute', 'OHDSI Attribute', $element_datatype);
        } elseif (substr($element_name, -5)=="_date") {
            dropdown_option('date', 'Date', $element_datatype);
        } elseif (substr($element_name, -9)=="_datetime") {
            dropdown_option('timestamp', 'Timestamp', $element_datatype);
        }
        dropdown_option('string', 'String', $element_datatype);
        dropdown_option('numeric', 'Numeric', $element_datatype);
        dropdown_option('ignore', 'N/A', $element_datatype);
        echo '</select>&nbsp;<select name="schema_subtype[]" id="' . $row['ordinal_position'] . '"';
        if ( ($element_datatype!='date') && ($element_datatype!='timestamp') ) {
            echo ' style="display:none;"';
        }
        echo '>';
        $element_subtype = $row['data_subtype'];
        dropdown_option('MM/DD/YYYY', 'MM/DD/YYYY', $element_subtype);
        dropdown_option('YYYY-MM-DD', 'YYYY-MM-DD', $element_subtype);
        echo '</select></td>';
    } else {
        // Add hidden element as placeholder for schema
        echo '<td><input type="hidden" name="schema_element[]" id="' . $row['sequence'] . '"></td>';
        echo '<input type="hidden" name="schema_subtype[]" id="' . $row['sequence'] . '">';
    }
    // $ignore = ($element_datatype == 'ignore');
    $ignore = false;
    // TODO - could just hide these elements but would need to put hidden elements instead to maintain offsets
    // May also need to implement in javascript/jquery to make it more interactive
    checkbox_group('required', $row_number, '', $required, ($disabled || $ignore), true);
    textbox_group('heading', $row_number, $row['display_heading'], 20, $ignore, true);
    if ($org_specific) {
        textbox_group('field_name', $row_number, $row['field_name'], 20, $ignore, true);
    }
    echo "</tr>\n";
}

// This function displays the available output fields to display in the silver and gold tables shown
// on the details (pipeline) page and the associated heading label.  It is also where the user designates
// which fields to be included in the output/export to the S3 buckets to be made available for SBPLA
function display_config_output($db, $domain, $org_specific=false):void
{
    // get the columns from the gold table schema for this domain
    $query_schema = "select column_name, ordinal_position from information_schema.columns ";
    $query_schema .= "where table_schema='gold' and table_name='" . $domain . "' ";
    $query_schema .= "order by ordinal_position;";
    $result_schema = pg_query($db, $query_schema);

    // get field display output settings from config table
    $query_display = "select column_name, display_heading from config.field_display ";
    $query_display .= "where config_domain='{$domain}' and ordinal_position>0 order by ordinal_position;";
    // echo 'Display fields query: ' . $query_display;
    $result_display = pg_query($db, $query_display);

    // get output fields from config table
    $query_output = "select column_name, display_heading from config.field_output ";
    $query_output .= "where config_domain='{$domain}' and ordinal_position>0 order by ordinal_position;";
    // echo 'Output fields query: ' . $query_output;
    $result_output = pg_query($db, $query_output);

    // display list of fields with options
    $row_display = pg_fetch_assoc($result_display);
    $row_output = pg_fetch_assoc($result_output);
    if ($result_schema) {
        $row_number = 0;
        while ($row_schema = pg_fetch_assoc($result_schema)) {
            echo '<tr><td>' . $row_schema['column_name'] . '</td>';

            // get display_silver settings from config.fields_display
            $display_heading = "";
            if ($row_schema['column_name'] == $row_display['column_name']) {
                checkbox_group('display_silver', $row_number, '', true, $org_specific, true);
                $display_heading = $row_display['display_heading'];
                $row_display = pg_fetch_assoc($result_display);
            } else {
                checkbox_group('display_silver', $row_number, '', false, $org_specific, true);
            }

            // get sbpla_output settings from config.fields_output
            if ($row_schema['column_name'] == $row_output['column_name']) {
                checkbox_group('sbpla_output', $row_number, '', true, $org_specific, true);
                echo "<td><input type='text' size='20' name='heading_output[]' id='{$row_number}' ";
                echo "value='{$row_output['display_heading']}'" . ($org_specific ? ' disabled' : '') . "></td></tr>\n";
                $row_output = pg_fetch_assoc($result_output);
            } else {
                checkbox_group('sbpla_output', $row_number, '', false, $org_specific, true);
                echo "<td><input type=\"text\" size=\"20\" name=\"heading_output[]\" id=\"{$row_number}\" ";
                echo " value=\"{$display_heading}\"" . ($org_specific ? ' disabled' : '') . "></td></tr>\n";
            }
            $row_number++;
        }
    }
}

// Function called from config page when user presses "save" to store the display and output settings
// for this domain (and, if org-specific, org) into the relevant config tables
function save_config($db, $elements, $domain, $org, $org_specific=false):void
{
    // remove any prior import config for this domain
    clear_config($db, $org, $domain, 'field_import', $org_specific);

    // get schema column names for raw source table
    $org_schema = ($org_specific ? $org : 'cdm');
    $query = "select column_name, ordinal_position from information_schema.columns ";
    $query .= "where table_schema='" . $org_schema . "' and table_name='" . $domain . "' ";
    $query .= "order by ordinal_position;";
    $result = pg_query($db, $query);

    // save field import configuration and display data
    if ($result) {
        $x = 0;
        $required_index = 0;
        $org_save = ($org_specific ? $org : 'cdm');
        while ($row = pg_fetch_assoc($result)) {
            // get field type and subtype from $_POST elements array
            $field_type = $elements['schema_element'][$x];
            $field_subtype = ( $field_type=='date' || $field_type=='timestamp' ? $elements['schema_subtype'][$x] : '' );

            $query = "insert into config.field_import (config_domain, config_org, column_name, data_type, ";
            $query .= "data_subtype, required, display_heading, field_name, ordinal_position) ";
            // $query .= "required, display_heading, field_name, ordinal_position) ";
            $query .= "values ('{$domain}', '{$org_save}', '{$row['column_name']}', ";
            $query .= "'{$field_type}', '{$field_subtype}', ";
            if ($elements['required'][$required_index] == $x) {
                $query .= 'true';
                $required_index++;
            } else {
                $query .= 'false';
            }
            $query .= ", '" . $elements['heading'][$x] . "', '" . $elements['field_name'][$x] . "', ";
            $query .= $row['ordinal_position'] . ");";
            // echo 'Save configuration query: ' . $query . "<br/>";
            pg_query($db, $query);
            $x++;
        }
    }

    // remove any prior display and output field config info for this domain
    // TODO use PostgreSQL upsert functionality (on conflict clause) instead
    clear_config($db, $org, $domain, 'field_display', $org_specific);
    clear_config($db, $org, $domain, 'field_output', $org_specific);

    // get domain attributes from config.domain table, specifically if this domain will have selectable rows
    $query = "select primary_key, select_for_mapping from config.domain where name='{$domain}';";
    $result = pg_query($db, $query);
    $row = pg_fetch_assoc($result);
    $primary_key = $row['primary_key'];
    // $map_concept = pg_bool($row['select_for_mapping']);

    // get schema column names for gold output table (which is the same as silver table)
    $query = "select column_name, ordinal_position from information_schema.columns ";
    $query .= "where table_schema='gold' and table_name='" . $domain . "' ";
    $query .= "order by ordinal_position;";
    $result = pg_query($db, $query);

    // add hidden elements to both fields_display and fields_output tables
    //      -3 = source_org, -2 = inferred, -1 = primary key
    add_hidden_column($db, $domain, 'source_org', -3);
    add_hidden_column($db, $domain, $primary_key, -2);
    add_hidden_column($db, $domain, 'inferred', -1);

    // save remaining display and output fields in configuration table
    $x = 0;
    $display_index = 0;     // index into checkboxes for including in silver pipeline display
    $sbpla_index = 0;       // index into checkboxes for including in SBPLA S3 output
    while ($row = pg_fetch_assoc($result)) {
        // save record in config.field_display if "show in silver" selected for this element
        if ($elements['display_silver'][$display_index] == $x) {
            add_config_element($db, $domain, 'field_display', $row, $elements['heading_output'][$x]);
            $display_index++;
        }

        if ($elements['sbpla_output'][$sbpla_index] == $x) {
            add_config_element($db, $domain, 'field_output', $row, $elements['heading_output'][$x]);
            $sbpla_index++;
        }
        $x++;
    }
}

// Clear/initialize the config tables for the specified domain and, if specified, org
function clear_config($db, $org, $domain, $table, $org_specific=false):void
{
    // remove any prior import config for this domain
    $query = "delete from config.{$table} where config_domain='{$domain}'";

    // $org-specific only applicable for field_import config table
    if ( ($table=='field_import') && $org_specific) {
        $query .= " and config_org='{$org}'";
    }
    $query .= ";";
    pg_query($db, $query);
}

// Helper function to add/save an individual field/element to the relevant config table
function add_config_element($db, $domain, $table, $row, $heading = ''):void
{
    $query = "insert into config.{$table} (config_domain, column_name, display_heading, ordinal_position) ";
    $query .= "values ('" . $domain . "', '" . $row['column_name'] . "', '";
    $query .= $heading . "', " . $row['ordinal_position'] . ");";
    // echo 'Save config element: ' . $query . '<br/>';
    pg_query($db, $query);
}

function add_hidden_column($db, $domain, $value, $position):void
{
    // add specified value into field_display table for silver zone
    $query = "insert into config.field_display (config_domain, column_name, ordinal_position) ";
    $query .= "values ('{$domain}', '{$value}', $position);";
    pg_query($db, $query);

    // add specified value into field_output table for gold zone
    $query = "insert into config.field_output (config_domain, column_name, ordinal_position) ";
    $query .= "values ('{$domain}', '{$value}', $position);";
    pg_query($db, $query);
}

// Build SQL statement to transform raw to refined (silver) zone
//-------------------------------------------------------------------------------------------
// **** THIS CODE IS LIKELY THE MOST COMPLEX IN THE PROJECT *****
// it is used to build the SQL that maps the bronze data to silver AND...
// to create the SQL that's used to "harmonize" concepts to the "target" vocabulary AND...
// generate the DDL for the silver and gold tables.
//
// The business logic for generating the INSERT SQL statement for populating the silver table(s)
// from the bronze data consists of building 3 seperate clauses - the INSERT clause, the SELECT
// clause, and the WHERE clause.  There is also logic that generates the ON CONFLICT clause so the
// INSERT behaves like an upsert in PostgreSQL - we'll need to understand how this might have to
// change if we move to Aurora or another RDS RDBMS technology.
//
// Separate subroutines (functions) are called for generating the relevant sub-clauses for each
// section of SQL which are called from this "parent" function and included below in this PHP code.
//
// TODO - the logic to build the SQL for the person table is not constructed here (yet).
//-------------------------------------------------------------------------------------------
function build_pipeline_sql($db, $post_elements, $domain, $org = NULL, $org_specific = false): string
{
    $org_save = ( $org_specific ? $org : '$org');
    // initialize the INSERT, SELECT, FROM, and GROUP BY components of the SQL that will be generated
    // to map/denormalize the data in the bronze zone to the silver and gold zones
    $sql_insert = "\tinsert into silver.{$domain} (source_org, inferred";
    $sql_select = ")\n\tselect '{$org_save}', false";
    // always pull in deidentification table to get upn_id and days_offset for date obfuscation
    // "br" is bronze table, "ob" is obfuscation table (which includes offset days field)
    $sql_from = "{$org_save}.{$domain} br inner join deidentify.obfuscation ob on cast(br.person_id as varchar)=ob.person_id and ob.source_org='{$org_save}'";
    $sql_group = "\ngroup by";

    // initialize the SELECT portion of the SQL that will be generated to map/harmonize data
    $sql_mapping = "\n\tselect '{$org_save}', true";

    // build DDL for silver and gold tables
    $ddl = "create table silver.{$domain} (";
    $ddl .= "\n\tsource_org varchar NOT NULL";
    $ddl .= ", \n\tinferred bool null";

    // get domain config fields from config.domain
    $domain_attributes = get_domain_attributes($db, $domain);

    // get column names for specified org and domain - use CDM schema if not org-specific
    $org_table = ($org_specific ? $org : 'cdm');
    $query = "select column_name, ordinal_position from information_schema.columns ";
    $query .= "where table_schema='{$org_table}' and table_name='{$domain}' ";
    $query .= "order by ordinal_position;";
    $result = pg_query($db, $query);

    // start building the ETL SQL (mapping from bronze to silver) to ALWAYS include the primary key for
    // this domain/table and the UPN_ID of the associated patient/person
    if ($result) {
        // first column should be primary key
        $row = pg_fetch_assoc($result);
        $sql_insert .= ', ' . $row['column_name'];
        $sql_mapping .= ', ' . $row['column_name'];
        $sql_group .= ' br.' . $row['column_name'];
        if ($domain=='person') {
            $sql_select .= ', br.' . $row['column_name'];
            $ddl .= ", \n\t" . $row['column_name'] . ' varchar NOT NULL';
        } else {
            $sql_select .= ', cast(br.' . $row['column_name'] . ' as int8)';
            $ddl .= ", \n\t" . $row['column_name'] . ' int8 NOT NULL';
        }

        // next column is always upn_id
        $sql_insert .= ', upn_id';
        $sql_mapping .= ', upn_id';
        $sql_select .= ', ob.upn_id';
        $sql_group .= ', ob.upn_id';
        $ddl .= ", \n\tupn_id int8 NOT NULL";

        // go through the rest of the fields in the domain/table and build the relevant SQL sections
        // according to the associated field type.  All fields will be copied from raw to silver.  Additional
        // transformation will be performed including casting dates/timestamps and offsetting dates based on
        // the value in the obfuscation file; denormalizing OHDSI concepts to include name/description and
        // other attributes, as relevent; adding attributes from relevant child tables (i.e., death, location, provider)
        //
        // these complex transformation are performed in the helper functions for each clause of the SQL that are
        // called from this function.  that code and documentation is found in each of those functions.
        $column = 1;
        $index_array = ['concept' => 1, 'location' => 1, 'provider' => 1];
        while ($row = pg_fetch_assoc($result)) {
            $column_name = $row['column_name'];
            $column_type = $post_elements['schema_element'][$column];
            $column_subtype = $post_elements['schema_subtype'][$column];

            // skip column if ignore specified
            if ($column_type != 'ignore') {
                $sql_insert .= sql_insert_clause($column_name, $column_type, $domain_attributes);
                $sql_mapping .= sql_mapping_clause($column_name, $column_type);
                $sql_select .= sql_select_clause($column_name, $column_type, $column_subtype, $domain_attributes, $index_array);
                $sql_from = sql_from_replace($sql_from, $column_name, $column_type, $index_array);
                $sql_group .= sql_group_clause($column_name, $column_type, $index_array);
                $ddl .= ddl_add_columns($column_name, $column_type, $domain_attributes);

                // increment index on relevant child table
                if ($column_type=='location' || $column_type=='provider') {
                    $index_array[$column_type]++;
                } elseif (strpos($column_type, 'ohdsi_')!==false) {
                    $index_array['concept']++;
                }
            }
            $column++;
        }
    }

    // The remaining sections of this function are used to reassemble the SQL and DDL and store (upsert) them
    // into the applicable config tables.  In addition, if the DDL has changed for this domain, the silver
    // (and currently gold) tables for that domain are dropped and rebuilt, including the associated indices
    // and/or constraints.  This is very important for these tables to be performant.

    // upsert new SQL into config.pipeline_import_sql
    // $org_save = ($org_specific ? $org : 'cdm');
    $sql = $sql_insert . $sql_select . "\nfrom " . $sql_from . ($domain=='person' ? $sql_group : '') . ";";
    $sql_string = addslashes($sql);
    $query = "insert into config.pipeline_import_sql (domain, org, load_pipeline_sql, updated_datetime) ";
    // $query .= "values ('{$domain}', '{$org_save}', e'{$sql_string}', now()) ";
    $query .= "values ('{$domain}', '{$org_table}', e'{$sql_string}', now()) ";
    $query .= "on conflict (domain, org) do update set load_pipeline_sql=excluded.load_pipeline_sql, updated_datetime=now();";
    execute_query($db, $query);

    // upsert DDL and mapping SQL into config_pipeline_process_sql if this is for default org (cdm)
    if ($org_table == 'cdm') {
        $ddl .= "\n);";
        $concept_name = $domain_attributes['concept_name'];
        $concept_vocab = $domain_attributes['default_vocab'];
        $sql_mapping = $sql_insert . ")" . $sql_mapping;
        $sql_mapping .= "\nfrom (silver.{$domain} s left join ((athena.concept_relationship cr inner join athena.concept c2 on cr.concept_id_2=c2.concept_id and c2.vocabulary_id='{$concept_vocab}') ";
        $sql_mapping .= "\n\t\tinner join config.relationship_type rt on cr.relationship_id=rt.relationship_id and rt.use_for_mapping) ";
        $sql_mapping .= "\n\t\ton s.{$concept_name}_concept_id=cr.concept_id_1) ";
        $sql_mapping .= "\n\tleft join (athena.concept_ancestor ca inner join athena.concept c1 on ca.ancestor_concept_id=c1.concept_id and c1.vocabulary_id='{$concept_vocab}') ";
        $sql_mapping .= "\n\t\ton s.{$concept_name}_concept_id=ca.descendant_concept_id ";
        $sql_mapping .= "\nwhere s.{$concept_name}_concept_vocabulary_id!='{$concept_vocab}' ";
        $sql_mapping .= "and source_org='{$org_save}' and not s.inferred and ";
        $sql_mapping .= "\n\t(cr.concept_id_1 is not null or ca.min_levels_of_separation=1) ";
        $sql_mapping .= "\non conflict do nothing;";
        $sql_mapping = addslashes($sql_mapping);

        $query = "insert into config.pipeline_process_sql (domain, create_table_ddl, insert_mapped_sql) ";
        $query .= "values ('{$domain}', e'{$ddl}', e'{$sql_mapping}') ";
        $query .= "on conflict (domain) do update set create_table_ddl=excluded.create_table_ddl, ";
        $query .= "insert_mapped_sql=excluded.insert_mapped_sql;";
        // echo 'Insert pipeline process SQL: ' . $query;
        execute_query($db, $query);

        // if $org is default CDM, drop and create silver table
        // check to make sure table exists first (in case it was previously dropped and create failed)
        $query = "select exists (select from pg_tables where schemaname='silver' and tablename='{$domain}');";
        $result = pg_query($db, $query);
        if (pg_fetch_result($result, 'exists')==='t') {
            execute_query($db, "DROP TABLE silver.\"{$domain}\";");
        }
        execute_query($db, $ddl);

        // add primary key constraint to silver table
        $constraint = "alter table silver.{$domain} add constraint silver_{$domain}_pk PRIMARY KEY ";
        $constraint .= "(upn_id, {$domain_attributes['primary_key']}";
        if (!in_array($domain, ['person', 'visit'])) {
            $constraint .= ", {$domain_attributes['concept_name']}_concept_id";
        }
        $constraint .= ");";
        // echo 'Add primary key in silver table: ' . $constraint;
        execute_query($db, $constraint);

        // Compare columns in silver and gold tables and add/aLter gold table accordingly
        $query = "select c1.column_name, c1.udt_name, c2.udt_name as target_type from ";
        $query .= "(select column_name, udt_name from information_schema.columns c ";
        $query .= "where c.table_schema = 'silver' and c.table_name='{$domain}') c1 ";
        $query .= "left join (select column_name, udt_name from information_schema.columns c ";
        $query .= "where c.table_schema = 'gold' and c.table_name='{$domain}') c2 ";
        $query .= "on c1.column_name = c2.column_name where c2.column_name is null ";
        $query .= "or c1.udt_name != c2.udt_name;";
        // echo 'Silver/gold table comparison: ' . $query;
        $result = execute_query($db, $query);

        // if there are any additional columns, construct ALTER TABLE statement
        if (pg_num_rows($result) > 0) {
            $alter = "ALTER TABLE gold.{$domain} ";

            // add or alter first column (target_type field will be NULL if this is a new row)
            $row = pg_fetch_assoc($result);
            $alter .= alter_table_entry($row['column_name'], $row['udt_name'], $row['target_type']);

            // add remaining columns
            while ($row = pg_fetch_assoc($result)) {
                $alter .= ", " . alter_table_entry($row['column_name'], $row['udt_name'], $row['target_type']);
            }
            $alter .= ";";
            // echo "Alter gold table: " . $alter;
            execute_query($db, $alter);
        }
    }
    return $sql;
}

// Helper function to build ALTER TABLE row
function alter_table_entry($column_name, $column_type, $modify):string {
    if ($modify) {
        $alter = "ALTER COLUMN {$column_name} TYPE {$column_type} USING {$column_name}::";
        switch ($column_type) {
            case 'int4':
                $alter .= "int";
                break;
            case 'int8':
                $alter .= "bigint";
                break;
            default:
                $alter .= $column_type;
        }
    } else {
        $alter = "ADD COLUMN {$column_name} {$column_type} NULL";
    }
    return $alter;
}

// This is where the complex SQL mapping work is done... for most fields, we just need to add a clause to the
// INSERT section of the SQL to include that field/element in the silver table.  The exceptions are handled in
// the if/then and switch statement - for birth_datetime (only found in the PERSON table), there are a bunch
// of additional fields derived from that date and from the child DEATH table that need to be accommodated.  For
// OHDSI concepts, there are additional attributes (name/description, vocabulary, standard_concept, etc.).  For
// location field types, we denormalize/add the zip code from the location table.  This may be expanded in the
// future.  This is also where we'd add logic for the provider field attributes when we get that data in the future.
function sql_insert_clause($column_name, $column_type, $attributes): string
{
    $sql_insert = ', ' . $column_name;
    if ($column_name=='birth_datetime') {
        $sql_insert .= ', age_years, death_date, death_datetime, death_type_concept_id';
	    $sql_insert .= ', death_type_name, death_cause_concept_id, death_cause_name';
    } else {
        switch ($column_type) {
            case 'ohdsi_concept':
                // if the concept_name and default vocabulary for this table isn't set, treat this like an attribute
                // TODO - don't even include OHDSI Concept as option for fields/tables where it's not relevant
                if (is_null($attributes['default_vocab'])) {
                    $name_field = str_replace('id', 'name', $column_name);
                    $sql_insert .= ', ' . $name_field;
                } else {
                    $concept_name = $attributes['concept_name'];
                    $sql_insert .= ", {$concept_name}_concept_name, {$concept_name}_concept_vocabulary_id, {$concept_name}_concept_code, ";
                    $sql_insert .= "{$concept_name}_concept_standard, {$concept_name}_concept_" . strtolower($attributes['default_vocab']);
                }
                break;

            case 'ohdsi_attribute':
                $name_field = str_replace('id', 'name', $column_name);
                $sql_insert .= ', ' . $name_field;
                break;

            case 'location':
                $zip_field = str_replace('id', 'zip', $column_name);
                $sql_insert .= ', ' . $zip_field;
                break;
        }
    }
    return $sql_insert;
}

// The SELECT clause for the mapping SQL follows similar logic to the INSERT section.  The major difference is
// this is where the complex mappings occur:
//    * obfuscating dates (adding the offset for this patient found in the obfusction table).  If that date or
//      date/time field is NULL, leave it NULL so as not to divulge the offset for other dates,
//    * coalescing dates (to handle situations where we get a datetime but not a date field,
//      as is the case for Providence with death date),
//    * transforming those obfuscated/coalesced dates/timestamps - sent as varchar strings - into
//      PostgreSQL date and timestamp values
//    * calculating age in years based on the obfuscated birth_datetime
//    * denormalizing OHDSI concepts, locations, and provider fields.  zip codes are truncated here for purposes
//      of HIPAA safe harbor obfuscation
//
function sql_select_clause($column_name, $column_type, $column_subtype, $attributes, $index_array): string
{
    // initialize return variable
    $sql_select = '';

    // construct SQL select clause based on column data type
    switch ($column_type) {
        case 'ohdsi_concept':
            $concept_index = $index_array['concept'];
            $target_vocab = $attributes['target_vocab'];
            $sql_select .= ", cast(br.{$column_name} as int4)";
            $sql_select .= ", a{$concept_index}.concept_name, a{$concept_index}.vocabulary_id, ";
            $sql_select .= "a{$concept_index}.concept_code, a{$concept_index}.standard_concept, \n";
            $sql_select .= "case when a{$concept_index}.vocabulary_id='{$target_vocab}' ";
            $sql_select .= "then a{$concept_index}.concept_code end";
            break;

        case 'ohdsi_attribute':
            $concept_index = $index_array['concept'];
            $sql_select .= ", cast(br.{$column_name} as int4)";
            $sql_select .= ", a{$concept_index}.concept_name";
            break;

        case 'date':
            $date_prefix = $attributes['date_prefix'];
            $start_end = (strpos($column_name, 'start')!==false ? '_start' :
                (strpos($column_name,'end')!==false ? '_end' : ''));
            $sql_select .= ", case when coalesce({$date_prefix}{$start_end}_date,'')='' then null else ";
            $sql_select .= "to_date({$date_prefix}{$start_end}_date, '{$column_subtype}') + ob.days_offset * interval '1 day' end";
            break;

        case 'timestamp':
            if ($column_name=='birth_datetime') {
                // birth date/time
                $sql_select .= ",\ncase when coalesce({$column_name},'')='' then null else ";
                $sql_select .= "to_timestamp({$column_name}, '{$column_subtype} HH24:MI') + ob.days_offset * interval '1 day' end";

                // age years
                $sql_select .= ",\ncase when coalesce({$column_name},'')='' then null else ";
                $sql_select .= "date_part('year', age( to_timestamp({$column_name}, ";
                $sql_select .= "'{$column_subtype} HH24:MI') + ob.days_offset * interval '1 day' ) ) end";

                // death date
	            $sql_select .= ",\ncase when coalesce(death_date,'')='' then null else ";
                $sql_select .= "to_date(death_date, 'YYYY-MM-DD') + ob.days_offset * interval '1 day' end";

                // death date/time
                $sql_select .= ",\ncoalesce(to_timestamp(death_datetime, 'YYYY-MM-DD HH24:MI') + ob.days_offset * interval '1 day'";
                $sql_select .= ",\n\tto_timestamp(death_datetime, 'YYYY-MM-DD') + ob.days_offset * interval '1 day')";

                // death attributes
                $sql_select .= ",\ncast(death_type_concept_id as int4), dt.concept_name ";
                $sql_select .= ", cast(cause_concept_id as int4), dc.concept_name ";

            } else {
                $date_prefix = $attributes['date_prefix'];
                $start_end = (strpos($column_name, 'start') !== false ? '_start' :
                    (strpos($column_name, 'end') !== false ? '_end' : ''));
                $sql_select .= ", case when coalesce({$date_prefix}{$start_end}_datetime,'')='' then null else ";
                $sql_select .= "to_timestamp({$date_prefix}{$start_end}_datetime, '{$column_subtype} HH24:MI') + ob.days_offset * interval '1 day' end";
            }
            break;

        case 'location':
            $sql_select .= ", cast(br.{$column_name} as int8)";
            $location_index = $index_array['location'];
            $sql_select .= ", substring(l{$location_index}.zip,1,3)";
            break;

        case 'provider':
            $sql_select .= ", cast(br.{$column_name} as int8)";
            break;

        default:
            $sql_select .= ", br.{$column_name}";
    }
    return $sql_select;
}

// This function is where the FROM clause of the SQL is built.  The source table and mapping from person_id to
// UPN ID was already built during the initialization of the FROM clause.  This function adds other child tables
// needed to handle joins with the death, location, provider, and athena concept tables, as relevant.  Logic in
// the calling function maintains the index for the tables that might repeat, i.e., concept/location/provider.
function sql_from_replace($sql_from, $column_name, $column_type, $index_array): string
{
    if ($column_type=='location') {
        $location_index = $index_array['location'];
        $sql_from = "(" . $sql_from . ")\n\t";
        $sql_from .= "left join \$org.location l{$location_index} on br.{$column_name}=l{$location_index}.location_id";
    } elseif (strpos($column_type, 'ohdsi_')!==false) {
        $concept_index = $index_array['concept'];
        $sql_from = "(" . $sql_from . ")\n\t";
        $sql_from .= "left join athena.concept a{$concept_index} on cast(br.{$column_name} as int4)=a{$concept_index}.concept_id";
    } elseif ($column_name=='birth_datetime') {
        $sql_from = "(((" . $sql_from . ")\n\t";
        $sql_from .= "left join \$org.death de on br.person_id=cast(de.person_id as varchar))\n\t";
        $sql_from .= "left join athena.concept dt on cast(de.death_type_concept_id as int4)=dt.concept_id)\n\t";
        $sql_from .= "left join athena.concept dc on cast(de.cause_concept_id as int4)=dc.concept_id";
    }
    return $sql_from;
}

// This GROUP BY section of the SQL is only used for the person table and necessary because the person table
// has a primary key constraint of only allowing one record per UPN_ID.  We've seen situations where we get
// duplicate records in child tables (e.g., location, death) that result in duplicate patients from the JOINs
// performed elsewhere in the SQL.  This grouping logic takes care of that.
//
// NOTE: there may be better ways to cleanse/de-dup those children table to eliminate the adverse performance
// impact and added complexity of this logic.  This should be considered a future TODO.
//
function sql_group_clause($column_name, $column_type, $index_array): string
{
    $sql_group = ', br.' . $column_name;   // initialize return value

    switch ($column_type) {
        case 'ohdsi_concept':
            $concept_index = $index_array['concept'];
            $sql_group .= ", a{$concept_index}.concept_name, a{$concept_index}.vocabulary_id, ";
            $sql_group .= "a{$concept_index}.concept_code, a{$concept_index}.standard_concept";
            break;

        case 'ohdsi_attribute':
            $concept_index = $index_array['concept'];
            $sql_group .= ", a{$concept_index}.concept_name";
            break;

        case 'timestamp':
            if ($column_name=='birth_datetime') {
                $sql_group .= ", ob.days_offset, de.death_date, de.death_datetime, death_type_concept_id";
                $sql_group .= ", dt.concept_name, cause_concept_id, dc.concept_name";
            }
            break;

        case 'location':
            $location_index = $index_array['location'];
            $sql_group .= ", l{$location_index}.zip";
            break;
    }

    return $sql_group;
}

// This function is called to create the SELECT clause for each field that will be used in the SQL that
// performs the mapping of concepts to a "standardized" vocabulary.  It's pretty much 1:1 except for
// OHDSI concepts in which we need to see if we found the mapped concept in the concept_relationship (cr)
// table, in which case use the appropriate OHDSI concept associated that OHDSI table (c2), otherwise
// it must have come from the concept_ancestor table and use the OHDSI concept information for that (c1).
function sql_mapping_clause($column_name, $column_type): string
{
    $sql_mapping = '';   // initialize return value
    switch ($column_type) {
        case 'ohdsi_concept':
            $sql_mapping .= ",\ncase when cr.concept_id_1 is not null then c2.concept_id else c1.concept_id end";
            $sql_mapping .= ",\ncase when cr.concept_id_1 is not null then c2.concept_name else c1.concept_name end";
            $sql_mapping .= ",\ncase when cr.concept_id_1 is not null then c2.vocabulary_id else c1.vocabulary_id end";
            $sql_mapping .= ",\ncase when cr.concept_id_1 is not null then c2.concept_code else c1.concept_code end";
            $sql_mapping .= ",\ncase when cr.concept_id_1 is not null then c2.standard_concept else c1.standard_concept end";
            $sql_mapping .= ",\ncase when cr.concept_id_1 is not null then c2.concept_code else c1.concept_code end";
            break;

        case 'ohdsi_attribute':
            $sql_mapping .= ",\ncase when cr.concept_id_1 is not null then c2.concept_id else c1.concept_id end";
            $sql_mapping .= ",\ncase when cr.concept_id_1 is not null then c2.concept_name else c1.concept_name end";
            break;

        default:
            $sql_mapping = ', ' . $column_name;

    }
    return $sql_mapping;
}

// This is where the fields for the silver table associated with this domain are added, based on similar
// logic to the clauses above.  Specifically, all fields in the raw (bronze) zone are cloned into the silver
// zone (though for some like dates we transform their data type).  In the cases discussed earlier (birth_datetime,
// OHDSI concepts, location fields, provider fields) additional denormalization is done and the additional
// relevant fields are stipulated here.
function ddl_add_columns($column_name, $column_type, $attributes): string
{
    // log_event_message($db, 'Adding DDL columns', "Domain: {$domain}, column: {$column_name}, type: {$type}");
    $ddl = '';
    $concept = $attributes['concept_name'] . "_concept";
    switch ($column_type) {
        case 'ohdsi_concept':
            $ddl .= ", \n\t{$concept}_id int4 NULL";
            $ddl .= ", \n\t{$concept}_name varchar NULL";
            $ddl .= ", \n\t{$concept}_vocabulary_id varchar NULL";
            $ddl .= ", \n\t{$concept}_code varchar NULL";
            $ddl .= ", \n\t{$concept}_standard varchar NULL";
            $ddl .= ", \n\t{$concept}_" . strtolower($attributes['default_vocab']) . " varchar NULL";
            break;

        case 'ohdsi_attribute':
            $concept_name = str_replace('id', 'name', $column_name);
            $ddl .= ", \n\t{$column_name} int4 NULL";
            $ddl .= ", \n\t{$concept_name} varchar NULL";
            break;

        case 'date':
            $ddl .= ", \n\t{$column_name} date NULL";
            break;

        case 'timestamp':
            $ddl .= ", \n\t{$column_name} timestamp NULL";
            if ($column_name=='birth_datetime') {
                $ddl .= ", \n\tage_years float4 NULL";
                $ddl .= ", \n\tdeath_date date NULL";
                $ddl .= ", \n\tdeath_datetime timestamp NULL";
                $ddl .= ", \n\tdeath_type_concept_id int4 NULL";
                $ddl .= ", \n\tdeath_type_name varchar NULL";
                $ddl .= ", \n\tdeath_cause_concept_id int4 NULL";
                $ddl .= ", \n\tdeath_cause_name varchar NULL";
            }
            break;

        case 'location':
            $ddl .= ", \n\t{$column_name} int8 NULL";
            $location_zip = str_replace('id', 'zip', $column_name);
            $ddl .= ", \n\t{$location_zip} varchar NULL";
            break;

        case 'provider';
            $ddl .= ", \n\t{$column_name} int8 NULL";
            // strip off '_id' from column name
            $provider_prefix = substr($column_name, 0,-3);
            $ddl .= ", \n\t{$provider_prefix}_name varchar NULL";
            $ddl .= ", \n\t{$provider_prefix}_npi varchar NULL";
            break;

        case 'numeric':
            $ddl .= ", \n\t{$column_name} int8 NULL";
            break;

        default:
            $ddl .= ", \n\t{$column_name} varchar NULL";
    }
    return $ddl;
}

?>