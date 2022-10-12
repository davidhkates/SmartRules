<?php
    require_once "pipeline_functions.php";

    function run_pipeline($db, $org_summary)
    {
        delete_pipeline_tables($db, $org_summary);
        load_pipeline_bronze($db, $org_summary);
        load_pipeline_silver($db, $org_summary);
        harmonize_code_vocab($db, $org_summary);
        load_pipeline_gold($db, $org_summary);
        // To be tested later, as the S3 bucket needs to be parametrised
        // push_pipeline_tables($db);
    }

    $org_summary = getenv("HCP_ORGANIZATION") ?? "washu";
    run_pipeline($db, $org_summary);
?>
