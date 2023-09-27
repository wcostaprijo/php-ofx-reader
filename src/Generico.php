<?php

namespace WcDeveloper\PhpOfxReader;

use WcDeveloper\PhpOfxReader\TemplateInterface;

class Generico implements TemplateInterface
{
    /**
     * Main tag name of the ofx file without using <>
     *
     * @return string
     */
    public function rootTag() : string
    {
        return 'OFX';
    }

    /**
     * Enter the full path from the object to the transactions separated by ->
     *
     * @return string
     */
    public function mapTransactions() : string
    {
        return 'BANKMSGSRSV1->STMTTRNRS->STMTRS->BANKTRANLIST->STMTTRN';
    }

    /**
     * Map the following data below according to the ofx transactions
     * Type of data available:
     *  - money
     *  - date
     *  - string
     * 
     * Return array structure:
     * [
     *  'you-key-one' => ['key' => 'key-in-ofx-file', 'type' => 'type-of-date'],
     *  'you-key-two' => ['key' => 'key-in-ofx-file', 'type' => 'type-of-date'],
     * ]
     *
     * @return array
     */
    public function mapTransaction() : array
    {
        return [
            'valor' => ['key' => 'TRNAMT', 'type' => 'money'],
            'identificacao'=> ['key' => 'CHECKNUM', 'type' => 'string'],
            'historico' => ['key' => 'MEMO', 'type' => 'string'],
            'data_extrato' => ['key' => 'DTPOSTED', 'type' => 'date'],
        ];
    }
}
