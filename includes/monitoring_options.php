<?php
$branchOptions = ["GSC", "GLA", "KID"];
$dealerOptions = ["MGSC", "NGSC", "MKC"];
$departmentOptions = ["Accounting", "Sales", "Service", "Parts", "BNC", "CNC", "Manila", "BRP"];
$moduleOptions = ["AMIS", "CMIS", "SMIS", "PMIS", "CSMS", "All Modules"];
$ticketModuleOptions = $moduleOptions;
$classificationOptions = ["User Error", "System Error", "Others"];
$approvedByOptions = ["NGR", "RGR"];
$processedByOptions = ["ITA", "LBA"];
$processedTypeOptions = ["Cancellation", "Unposting", "Void", "Data Correction", "Adjustment", "Others"];
$statusOptions = ["Pending", "Cancelled", "Unposted", "Voided"];
$summaryStatusOptions = [...$statusOptions, "Done"];
$ticketStatusOptions = ["Open", "In Progress", "On Hold", "Resolved"];
$monitoringSummaryRowsPerPageOptions = [10];
$rowsPerPageOptions = [25, 50, 100];

$summaryColumns = [
    ["key" => "data_correction_alert", "label" => "Alert", "format" => "text"],
    ["key" => "disciplinary_action", "label" => "Action", "format" => "action_control"],
    ["key" => "identification_number", "label" => "ID Number", "format" => "record_link"],
    ["key" => "date_recorded", "label" => "Date", "format" => "date"],
    ["key" => "transaction_date", "label" => "Transaction Date", "format" => "date"],
    ["key" => "branch", "label" => "Branch", "format" => "text"],
    ["key" => "dealer", "label" => "Dealers", "format" => "text"],
    ["key" => "department", "label" => "Department", "format" => "text"],
    ["key" => "module", "label" => "Module", "format" => "text"],
    ["key" => "user_name", "label" => "User", "format" => "text"],
    ["key" => "invoice_reference", "label" => "Invoice Reference", "format" => "text"],
    ["key" => "client_name", "label" => "Client Name", "format" => "text"],
    ["key" => "payment_reference", "label" => "Payment Reference", "format" => "text"],
    ["key" => "amount", "label" => "Amount", "format" => "amount"],
    ["key" => "reason", "label" => "Reason", "format" => "text"],
    ["key" => "approved_by", "label" => "Approved By", "format" => "text"],
    ["key" => "processed_type", "label" => "Processed Type", "format" => "text"],
    ["key" => "processed_by", "label" => "Processed By", "format" => "text"],
    ["key" => "remarks", "label" => "Remarks", "format" => "text"],
    ["key" => "classification", "label" => "Classification", "format" => "text"],
    ["key" => "system_admin", "label" => "System Admin", "format" => "text"],
    ["key" => "ticket", "label" => "Ticket", "format" => "text"],
    ["key" => "status", "label" => "Status", "format" => "text"],
    ["key" => "offense", "label" => "Offense", "format" => "text"],
    ["key" => "created_at", "label" => "Encoded At", "format" => "timestamp"],
];

$monitoringDetailFields = [
    ["key" => "identification_number", "label" => "ID Number", "format" => "text"],
    ["key" => "date_recorded", "label" => "Date", "format" => "date"],
    ["key" => "transaction_date", "label" => "Transaction Date", "format" => "date"],
    ["key" => "branch", "label" => "Branch", "format" => "text"],
    ["key" => "dealer", "label" => "Dealers", "format" => "text"],
    ["key" => "department", "label" => "Department", "format" => "text"],
    ["key" => "module", "label" => "Module", "format" => "text"],
    ["key" => "user_name", "label" => "User", "format" => "text"],
    ["key" => "invoice_reference", "label" => "Invoice Reference", "format" => "text"],
    ["key" => "client_name", "label" => "Client Name", "format" => "text"],
    ["key" => "payment_reference", "label" => "Payment Reference", "format" => "text"],
    ["key" => "amount", "label" => "Amount", "format" => "amount"],
    ["key" => "reason", "label" => "Reason", "format" => "text"],
    ["key" => "approved_by", "label" => "Approved By", "format" => "text"],
    ["key" => "processed_type", "label" => "Processed Type", "format" => "text"],
    ["key" => "processed_by", "label" => "Processed By", "format" => "text"],
    ["key" => "remarks", "label" => "Remarks", "format" => "text"],
    ["key" => "classification", "label" => "Classification", "format" => "text"],
    ["key" => "system_admin", "label" => "System Admin", "format" => "text"],
    ["key" => "ticket", "label" => "Ticket", "format" => "text"],
    ["key" => "status", "label" => "Status", "format" => "text"],
    ["key" => "offense", "label" => "Offense", "format" => "text"],
    ["key" => "data_correction_alert", "label" => "Alert", "format" => "text"],
    ["key" => "disciplinary_action", "label" => "Disciplinary Action", "format" => "text"],
    ["key" => "created_at", "label" => "Encoded At", "format" => "timestamp"],
];

$ticketMonitoringColumns = [
    ["key" => "date_created", "label" => "Date Created", "format" => "date"],
    ["key" => "branch", "label" => "Branch", "format" => "text"],
    ["key" => "dealer", "label" => "Dealers", "format" => "text"],
    ["key" => "module", "label" => "Module", "format" => "text"],
    ["key" => "ticket_number", "label" => "Ticket Number", "format" => "text"],
    ["key" => "ticket_description", "label" => "Description", "format" => "text"],
    ["key" => "created_by", "label" => "Created By", "format" => "text"],
    ["key" => "ticket_age", "label" => "Ticket Age", "format" => "ticket_age"],
    ["key" => "created_at", "label" => "Encoded At", "format" => "timestamp"],
    ["key" => "resolved_at", "label" => "Date Resolved", "format" => "date"],
    ["key" => "ticket_status", "label" => "Status", "format" => "text"],
];
