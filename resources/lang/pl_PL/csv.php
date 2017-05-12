<?php
/**
 * csv.php
 * Copyright (C) 2016 thegrumpydictator@gmail.com
 *
 * This software may be modified and distributed under the terms of the
 * Creative Commons Attribution-ShareAlike 4.0 International License.
 *
 * See the LICENSE file for details.
 */

declare(strict_types=1);

return [

    'import_configure_title' => 'Skonfiguruj import',
    'import_configure_intro' => 'There are some options for your CSV import. Please indicate if your CSV file contains headers on the first column, and what the date format of your date-fields is. That might require some experimentation. The field delimiter is usually a ",", but could also be a ";". Check this carefully.',
    'import_configure_form'  => 'Podstawowe opcje importu CSV',
    'header_help'            => 'Check this if the first row of your CSV file are the column titles',
    'date_help'              => 'Date time format in your CSV. Follow the format like <a href="https://secure.php.net/manual/en/datetime.createfromformat.php#refsect1-datetime.createfromformat-parameters">this page</a> indicates. The default value will parse dates that look like this: :dateExample.',
    'delimiter_help'         => 'Choose the field delimiter that is used in your input file. If not sure, comma is the safest option.',
    'import_account_help'    => 'If your CSV file does NOT contain information about your asset account(s), use this dropdown to select to which account the transactions in the CSV belong to.',
    'upload_not_writeable'   => 'The grey box contains a file path. It should be writeable. Please make sure it is.',

    // roles
    'column_roles_title'     => 'Zdefiniuj role dla kolumn',
    'column_roles_table'     => 'Tabela',
    'column_name'            => 'Nazwa kolumny',
    'column_example'         => 'Przykładowe dane w kolumnie',
    'column_role'            => 'Znaczenie danych w kolumnie',
    'do_map_value'           => 'Zmapuj te wartości',
    'column'                 => 'Kolumna',
    'no_example_data'        => 'Brak dostępnych danych przykładowych',
    'store_column_roles'     => 'Kontynuuj import',
    'do_not_map'             => '(nie mapuj)',
    'map_title'              => 'Połącz dane z importu z danymi z Firefly III',
    'map_text'               => 'In the following tables, the left value shows you information found in your uploaded CSV file. It is your task to map this value, if possible, to a value already present in your database. Firefly will stick to this mapping. If there is no value to map to, or you do not wish to map the specific value, select nothing.',

    'field_value'          => 'Wartość pola',
    'field_mapped_to'      => 'Zmapowane do',
    'store_column_mapping' => 'Zapisz mapowanie',

    // map things.


    'column__ignore'                => '(ignoruj tę kolumnę)',
    'column_account-iban'           => 'Konto aktywów (IBAN)',
    'column_account-id'             => 'ID konta aktywów (taki sam jak w Firefly)',
    'column_account-name'           => 'Konto aktywów (nazwa)',
    'column_amount'                 => 'Kwota',
    'column_amount-comma-separated' => 'Kwota (przecinek jako separator dziesiętny)',
    'column_bill-id'                => 'ID rachunku (taki sam jak w Firefly)',
    'column_bill-name'              => 'Nazwa rachunku',
    'column_budget-id'              => 'ID budżetu (taki sam jak w Firefly)',
    'column_budget-name'            => 'Nazwa budżetu',
    'column_category-id'            => 'ID kategorii (taki sam jak w Firefly)',
    'column_category-name'          => 'Nazwa kategorii',
    'column_currency-code'          => 'Kod waluty (ISO 4217)',
    'column_currency-id'            => 'ID waluty (taki sam jak w Firefly)',
    'column_currency-name'          => 'Nazwa waluty (taka sama jak w Firefly)',
    'column_currency-symbol'        => 'Symbol waluty (taki sam jak w Firefly)',
    'column_date-interest'          => 'Interest calculation date',
    'column_date-book'              => 'Data księgowania transakcji',
    'column_date-process'           => 'Transaction process date',
    'column_date-transaction'       => 'Data',
    'column_description'            => 'Opis',
    'column_opposing-iban'          => 'Opposing account (IBAN)',
    'column_opposing-id'            => 'Opposing account ID (matching Firefly)',
    'column_external-id'            => 'Zewnętrzne ID',
    'column_opposing-name'          => 'Opposing account (name)',
    'column_rabo-debet-credit'      => 'Rabobank specific debet/credit indicator',
    'column_ing-debet-credit'       => 'ING specific debet/credit indicator',
    'column_sepa-ct-id'             => 'SEPA Credit Transfer end-to-end ID',
    'column_sepa-ct-op'             => 'SEPA Credit Transfer opposing account',
    'column_sepa-db'                => 'SEPA Direct Debet',
    'column_tags-comma'             => 'Tagi (oddzielone przecinkami)',
    'column_tags-space'             => 'Tagi (oddzielone spacjami)',
    'column_account-number'         => 'Konto aktywów (numer konta)',
    'column_opposing-number'        => 'Opposing account (account number)',
];