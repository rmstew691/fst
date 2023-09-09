<?php
// require "config.php";

require "rt_config.php";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.13.2/jquery-ui.min.js"></script>
    <link rel="stylesheet" href="https://ajax.googleapis.com/ajax/libs/jqueryui/1.13.2/themes/smoothness/jquery-ui.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <link rel="shortcut icon" type="image/x-icon" href="images/PW_P Logo.png" />
    <link rel="stylesheet" href="stylesheets/element-styles.css?<?= $version; ?>1">
    <link rel="stylesheet" href="stylesheets/dashboard-styles.css?<?= $version; ?>">
    <style>
        /**styles search divs */
        .search_div {
            padding: 1em;
            float: left;
        }

        .search_h4 {
            margin-bottom: 10px;
        }

        .search_table input[type=checkbox] {
            -ms-transform: scale(1.2);
            -webkit-transform: scale(1.2);
            transform: scale(1.2);
        }

        /**style note next to download button */
        #download_note {
            font-weight: normal;
            font-size: 16px;
            padding-left: 1em;
        }

        /**style autocomplete used in work order parts request menu */
        .ui-autocomplete {
            position: absolute;
            cursor: default;
            z-index: 4000 !important
        }

        .ui-autocomplete {
            max-height: 300px;
            overflow-y: auto;
            /* prevent horizontal scrollbar */
            overflow-x: hidden;
        }

        /**style stock class (used when selecting & requesting parts) */
        .stock {
            text-align: center;
            font-weight: bold;
        }

        /*set width based on group_class*/
        .all_class tbody {
            width: 2160px;
            height: 750px;
        }

        /** updating padding of profile tab */
        #profile {
            padding-top: 4em;
        }

        /**styles added to profile settings table on profile tab */
        #profileTable {
            border-collapse: collapse;
        }

        #profileTable td {
            border: 1px solid #000000;
            padding: 5px;
        }

        #profileTable th {

            padding: 5px;
        }

        /* style element in new part dialog box*/
        .newPart_input {
            width: 400px;
        }

        .newPart_th {
            text-align: left;
        }

        .new_project_button {
            font-size: 20px;
            width: 10em;
            height: 2em;
            text-align: center;
        }

        /** style widths of columns & input fields */
        .col_quote_num {
            width: 150px;
        }

        .col_value {
            width: 120px;
            display: <?= $deployHide ?>;
        }

        .input_value {
            display: <?= $deployHide ?>;
        }

        /**shows elements that hide for deployment */
        .hide_for_deployment {
            display: <?= $deployHide ?>;
        }

        .col_project_name {
            width: 360px;
        }

        .col_vp_num {
            width: 120px;
        }

        .col_vp_contract {
            width: 120px;
        }

        .col_designer {
            width: 140px;
        }

        .col_quote_creator {
            width: 141px;
        }

        .col_project_type {
            width: 230px;
        }

        .col_state {
            width: 60px;
        }

        .col_market {
            width: 100px;
        }

        .col_customer {
            width: 150px;
        }

        .col_quote_status {
            width: 150px;
        }

        .col_last_update {
            width: 185px;
        }

        #partsTable {
            width: 60%;
            border-collapse: separate;
            margin-left: 10%;
        }

#bdaKit{
            width: 60%;
            border-collapse: separate;
            margin-left: 10%;
        }
        

        #msg {
            margin-left: 10%;
            padding-top: 10px;
        }

        label {
            font-family: sans-serif;
            font-size: 1rem;
            padding-right: 10px;
        }

        select {
            padding: 2px 5px;
            width: 6rem;
            margin-left: 10px;
        }

    </style>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.13.2/jquery-ui.min.js"></script>
    <link rel="stylesheet" href="https://ajax.googleapis.com/ajax/libs/jqueryui/1.13.2/themes/smoothness/jquery-ui.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <link rel="shortcut icon" type="image/x-icon" href="images/PW_P Logo.png" />
    <link rel="stylesheet" href="stylesheets/element-styles.css?<?= $version; ?>1">
    <link rel="stylesheet" href="stylesheets/dashboard-styles.css?<?= $version; ?>">

</head>

<body>
    <br>

    <table id="partsTable" border="2" style="border-collapse: collapse; font-family: sans-serif">
        <tr>

            <select id="bda_kit-select" style="margin-left: 10%; width:7%">
                <option value="0">Select Value</option>
                <option value="1">ADRF SDR (24dBm / .25W) </option>
                <option value="2">ADRF SDR (30dBm / 1W)</option>
                <option value="3">ADRF SDR (33dBm / 2W)</option>
                <option value="4">ADRF SDR (43dBm / 20W)</option>
                <option value="5">Commscope NodeA (27dBm / .5W)</option>
                <option value="6">Commscope NodeA (37dBm / 5W)</option>
                <option value="7">JMA Teko</option>
                <option value="8">SOLiD Alliance</option>
                <option value="9">ADRF ADXV</option>
                <option value="10">2W - JMA</option>
                <option value="11">10W - JMA</option>
                <option value="12">20W - JMA</option>
                <option value="13">30W - JMA</option>
                <option value="14">BLANK</option>
                <option value="15">5W - SOLiD (4 Bands)</option>
                <option value="16">20W - SOLiD (5 Bands)</option>
                <option value="17">2W - ADRF (2 Bands)</option>
                <option value="18">5W - ADRF (2 Bands)</option>
                <option value="19">20W - ADRF (4 Bands)</option>
            </select>
        </tr>
        <tr>
            <select id="pMarkup">
                <option value="">Select Value</option>
                <option value="1">5%</option>
                <option value="2">10%</option>
                <option value="3">20%</option>
                <option value="4">30%</option>
                <option value="5">40%</option>
                <option value="6">50%</option>
                <option value="7">60%</option>
                <option value="8">70%</option>
                <option value="9">80%</option>
            </select>
        <tr>
        <tr>
            <select id="aMarkup">
                <option value="-1">Select Value</option>
                <option value="1">5%</option>
                <option value="2">10%</option>
                <option value="3">20%</option>
                <option value="4">30%</option>
                <option value="5">40%</option>
                <option value="6">50%</option>
                <option value="7">60%</option>
                <option value="8">70%</option>
                <option value="9">80%</option>
            </select>
        <tr>
            <thead>
                <tr>

                    <th width="3%">ID</th>
                    <th width="3%">Quantity</th>
                    <th width="20%">Part Description</th>
                    <th width="10%">Description</th>
                    <th width="5%">Cost</th>
                    <th width="5%" class="calculation" title="Total Cost = Cost x Quantity">Total Cost</th>
                    <th width="5%" class="calculation" title="Price = Total Cost x P-Markup">Price</th>
                    <th width="15%">Part Number</th>
                </tr>
            </thead>

        </tr>
        <button onclick="populateTable()">Reset Table</button>
        <button onclick="updateTable()">Update</button>

    </table>
    <div id="msg"></div>
    <br>
    <div>
        <input type="text" id="bda_kit-input" name="search" placeholder="Search BDA Kit" style="margin-left: 10%">
    </div>

    <table id="bda_kit" border="2" style="border-collapse: collapse; margin-left:10%">
        <thead>
            <tr>
                <th width="3%">ID</th>
                <th width="20%">Part Description</th>
                <th width="10%">Part Number</th>
                <th width="5%">Cost</th>
                <!-- <th width="10%">Element Type</th>
                <th width="10%">Bda Number</th> -->
            </tr>
        </thead>
        <tbody>

        </tbody>
    </table>
    <br>
    <div>
        <table id="bda_kit_search">
    </div>



    <script src="updatedTable.js"></script>
    <script src="bdaKitTable.js"></script>
</body>

</html>