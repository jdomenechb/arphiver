<?php

/**
 * This file is part of the archiver package.
 *
 * (c) Jordi Domènech Bonilla
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Jdomenechb\Arphiver\Arphiver;

require 'vendor/autoload.php';

// --- CONFIG
$username = '';
$password = '';
$host = '';
$db = '';

// --- INIT
$connection = new PDO("mysql:dbname=$db;host=$host", $username, $password);
$connection->exec('SET NAMES utf8');

$archiver = new Arphiver($connection, [
    'db1.table1' => [
        'additionalForeignKeys' => [
            'nonfktable_id' => 'db1.nonfktable.id',
        ]
    ],

    'db1.table2' => [
        'mappedEntities' => [
            'table3' => 'table3_entity',
        ]
    ],

    'defaultMapToEntity' => function ($fieldName) {
        if (stripos($fieldName, '_id') === strlen($fieldName) - 3) {
            return substr($fieldName, 0, -3);
        }

        return $fieldName;
    }
]);

$result = $archiver->archive($db, 'table1', 'id = ?', [123456]);

if ($result === false) {
    echo json_last_error_msg();die;
}

file_put_contents('result.json', json_encode($result, JSON_PRETTY_PRINT));
